<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dating\MesajGonderRequest;
use App\Http\Resources\BildirimResource;
use App\Http\Resources\KullaniciResource;
use App\Http\Resources\MesajResource;
use App\Http\Resources\SohbetResource;
use App\Models\Engelleme;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\AyarServisi;
use App\Services\EslesmeServisi;
use App\Services\MesajServisi;
use App\Services\Odeme\MobilOdemeAyarServisi;
use App\Services\Users\UserOnlineStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MobileController extends Controller
{
    private const SYNC_CURSOR_PREFIX = 'mobile-sync:v1:';
    private const SYNC_MESSAGE_LIMIT = 200;
    private const SYNC_CONVERSATION_LIMIT = 50;
    private const SYNC_NOTIFICATION_LIMIT = 50;

    public function __construct(
        private AyarServisi $ayarServisi,
        private MobilOdemeAyarServisi $mobilOdemeAyarServisi,
        private EslesmeServisi $eslesmeServisi,
        private MesajServisi $mesajServisi,
        private UserOnlineStatusService $userOnlineStatusService,
    ) {}

    public function config(Request $request): JsonResponse
    {
        $payload = [
            'server_time' => now()->toISOString(),
            'config_ttl_seconds' => 86400,
            'public_settings' => $this->publicSettingsPayload(),
        ];
        $etag = '"'.sha1(json_encode($payload['public_settings'], JSON_UNESCAPED_UNICODE)).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->json(null, 304)->setEtag(trim($etag, '"'));
        }

        return response()
            ->json($payload)
            ->setEtag(trim($etag, '"'))
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = now();

        return response()->json([
            'server_time' => $now->toISOString(),
            'sync_token' => $now->toISOString(),
            'cache' => [
                'current_user_ttl_seconds' => 300,
                'conversation_ttl_seconds' => 30,
                'discover_ttl_seconds' => 300,
                'notification_ttl_seconds' => 60,
            ],
            'user' => KullaniciResource::make($user->fresh()->load('aiCharacter', 'fotograflar'))->resolve($request),
            'public_settings' => $this->publicSettingsPayload(),
            'match_summary' => $this->eslesmeServisi->merkez($user),
            'conversations' => SohbetResource::collection(
                $this->conversationQuery($user)->limit(20)->get()
            )->resolve($request),
            'discover_profiles' => KullaniciResource::collection(
                $this->discoverProfiles($user, 4)
            )->resolve($request),
            'notifications' => [
                'unread_count' => $this->todaysUnreadNotificationCount($user),
            ],
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sync_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $user = $request->user();
        $now = now();
        $syncState = $this->decodeSyncToken($validated['sync_token'] ?? null, CarbonImmutable::instance($now));
        $since = $syncState['since'];
        $checkpoint = $syncState['checkpoint'];
        $sinceForQuery = $since ? $this->syncDateForQuery($since) : null;
        $checkpointForQuery = $this->syncDateForQuery($checkpoint);
        $conversationIds = $this->conversationQuery($user)->pluck('sohbetler.id');

        $messageRows = Mesaj::query()
            ->whereIn('sohbet_id', $conversationIds)
            ->when($sinceForQuery, fn ($query, $sinceValue) => $query->where('created_at', '>', $sinceValue))
            ->where('created_at', '<=', $checkpointForQuery)
            ->when($syncState['message_id'], fn ($query, $messageId) => $query->where('id', '>', $messageId))
            ->with('gonderen:id,ad,kullanici_adi,profil_resmi,dil')
            ->orderBy('id')
            ->limit(self::SYNC_MESSAGE_LIMIT + 1)
            ->get();
        $messagesHasMore = $messageRows->count() > self::SYNC_MESSAGE_LIMIT;
        $messages = $messageRows->take(self::SYNC_MESSAGE_LIMIT)->values();

        $notificationRows = $user->notifications()
            ->whereDate('created_at', $now->toDateString())
            ->when($sinceForQuery, fn ($query, $sinceValue) => $query->where('updated_at', '>', $sinceValue))
            ->where('updated_at', '<=', $checkpointForQuery)
            ->when($syncState['notification_updated_at'], function ($query, CarbonImmutable $updatedAt) use ($syncState) {
                $updatedAtValue = $this->syncDateForQuery($updatedAt);
                $query->where(function ($inner) use ($updatedAtValue, $syncState) {
                    $inner->where('updated_at', '>', $updatedAtValue)
                        ->orWhere(function ($tie) use ($updatedAtValue, $syncState) {
                            $tie->where('updated_at', '=', $updatedAtValue)
                                ->where('id', '>', $syncState['notification_id']);
                        });
                });
            })
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(self::SYNC_NOTIFICATION_LIMIT + 1)
            ->get();
        $notificationsHasMore = $notificationRows->count() > self::SYNC_NOTIFICATION_LIMIT;
        $notifications = $notificationRows->take(self::SYNC_NOTIFICATION_LIMIT)->values();

        $conversationRows = $this->conversationQuery($user)
            ->when($sinceForQuery, fn ($query, $sinceValue) => $query->where('sohbetler.updated_at', '>', $sinceValue))
            ->where('sohbetler.updated_at', '<=', $checkpointForQuery)
            ->when($syncState['conversation_updated_at'], function ($query, CarbonImmutable $updatedAt) use ($syncState) {
                $updatedAtValue = $this->syncDateForQuery($updatedAt);
                $query->where(function ($inner) use ($updatedAtValue, $syncState) {
                    $inner->where('sohbetler.updated_at', '>', $updatedAtValue)
                        ->orWhere(function ($tie) use ($updatedAtValue, $syncState) {
                            $tie->where('sohbetler.updated_at', '=', $updatedAtValue)
                                ->where('sohbetler.id', '>', $syncState['conversation_id']);
                        });
                });
            })
            ->reorder('sohbetler.updated_at')
            ->orderBy('sohbetler.id')
            ->limit(self::SYNC_CONVERSATION_LIMIT + 1)
            ->get();
        $conversationsHasMore = $conversationRows->count() > self::SYNC_CONVERSATION_LIMIT;
        $conversations = $conversationRows->take(self::SYNC_CONVERSATION_LIMIT)->values();

        $hasMore = $messagesHasMore || $conversationsHasMore || $notificationsHasMore;
        $nextState = $this->nextSyncState(
            $syncState,
            checkpoint: $checkpoint,
            messages: $messages,
            conversations: $conversations,
            notifications: $notifications,
        );

        return response()->json([
            'server_time' => $now->toISOString(),
            'sync_token' => $hasMore ? $this->encodeSyncToken($nextState) : $checkpoint->toISOString(),
            'has_more' => $hasMore,
            'user' => KullaniciResource::make($user->fresh()->load('aiCharacter', 'fotograflar'))->resolve($request),
            'match_summary' => $this->eslesmeServisi->merkez($user),
            'conversations' => SohbetResource::collection($conversations)->resolve($request),
            'messages' => MesajResource::collection($messages)->resolve($request),
            'notifications' => [
                'unread_count' => $this->todaysUnreadNotificationCount($user),
                'items' => BildirimResource::collection($notifications)->resolve($request),
            ],
        ]);
    }

    public function messages(Request $request, Sohbet $conversation): JsonResponse
    {
        Gate::authorize('erisebilir', $conversation);

        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:1'],
            'before_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $afterId = $validated['after_id'] ?? null;
        $beforeId = $validated['before_id'] ?? null;

        $query = $conversation->mesajlar()
            ->with('gonderen:id,ad,kullanici_adi,profil_resmi,dil');

        if ($afterId) {
            $messages = $query
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } else {
            $messages = $query
                ->when($beforeId, fn ($inner) => $inner->where('id', '<', $beforeId))
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
        }

        return response()->json([
            'data' => MesajResource::collection($messages)->resolve($request),
            'meta' => [
                'limit' => $limit,
                'after_id' => $afterId,
                'before_id' => $beforeId,
                'has_more_older' => $messages->count() === $limit && ! $afterId,
            ],
            'ai' => [
                'status' => $conversation->ai_durumu,
                'status_text' => $conversation->ai_durum_metni,
                'planned_at' => $conversation->ai_planlanan_cevap_at?->toISOString(),
            ],
        ]);
    }

    public function sendMessage(MesajGonderRequest $request, Sohbet $conversation): JsonResponse
    {
        Gate::authorize('erisebilir', $conversation);

        $message = $this->mesajServisi->gonder(
            $conversation,
            $request->user(),
            $request->validated(),
        );

        return (new MesajResource($message->load('gonderen:id,ad,kullanici_adi,profil_resmi,dil')))
            ->response()
            ->setStatusCode($message->wasRecentlyCreated ? 201 : 200);
    }

    private function conversationQuery(User $user)
    {
        $userId = (int) $user->id;

        return Sohbet::query()
            ->whereHas('eslesme', function ($query) use ($userId) {
                $query->where('user_id', $userId)->orWhere('eslesen_user_id', $userId);
            })
            ->where('durum', 'aktif')
            ->with([
                'eslesme.user:id,ad,kullanici_adi,profil_resmi,cevrim_ici_mi,dil,hesap_tipi',
                'eslesme.user.aiCharacter:id,user_id,character_id,character_version,schema_version,active,display_name,primary_language_code,primary_language_name,model_name,character_json',
                'eslesme.eslesenUser:id,ad,kullanici_adi,profil_resmi,cevrim_ici_mi,dil,hesap_tipi',
                'eslesme.eslesenUser.aiCharacter:id,user_id,character_id,character_version,schema_version,active,display_name,primary_language_code,primary_language_name,model_name,character_json',
                'sonMesaj.gonderen:id,ad,kullanici_adi,profil_resmi,dil',
            ])
            ->withCount([
                'mesajlar as okunmamis_sayisi' => function ($query) use ($userId) {
                    $query->where('gonderen_user_id', '!=', $userId)
                        ->where('okundu_mu', false);
                },
            ])
            ->orderByDesc('son_mesaj_tarihi')
            ->orderByDesc('id');
    }

    private function discoverProfiles(User $user, int $limit)
    {
        $engellenen = Engelleme::query()
            ->where('engelleyen_user_id', $user->id)
            ->pluck('engellenen_user_id')
            ->merge(
                Engelleme::query()
                    ->where('engellenen_user_id', $user->id)
                    ->pluck('engelleyen_user_id')
            );

        $eslesilen = Eslesme::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('eslesen_user_id', $user->id);
            })
            ->where('durum', 'aktif')
            ->get(['user_id', 'eslesen_user_id'])
            ->flatMap(function (Eslesme $eslesme) use ($user) {
                return collect([$eslesme->user_id, $eslesme->eslesen_user_id])
                    ->reject(fn ($id) => (int) $id === (int) $user->id);
            });

        $excludedIds = $engellenen->merge($eslesilen)->push($user->id)->unique()->values();

        $this->syncAiDiscoverCandidates($excludedIds);

        return User::query()
            ->whereIn('hesap_tipi', ['user', 'ai'])
            ->where('hesap_durumu', 'aktif')
            ->where('cevrim_ici_mi', true)
            ->whereNotIn('id', $excludedIds)
            ->with([
                'fotograflar',
                'aiCharacter:id,user_id,character_id,character_version,schema_version,active,display_name,primary_language_code,primary_language_name,model_name,character_json',
            ])
            ->orderByRaw("CASE WHEN hesap_tipi = 'user' THEN 0 ELSE 1 END")
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    private function syncAiDiscoverCandidates($excludedIds): void
    {
        $aiUsers = User::query()
            ->where('hesap_tipi', 'ai')
            ->where('hesap_durumu', 'aktif')
            ->whereNotIn('id', $excludedIds)
            ->with([
                'aiCharacter:id,user_id,character_id,character_version,schema_version,active,display_name,primary_language_code,primary_language_name,model_name,character_json',
            ])
            ->get(['id', 'hesap_tipi', 'hesap_durumu', 'cevrim_ici_mi', 'son_gorulme_tarihi']);

        $this->userOnlineStatusService->syncCollection($aiUsers);
    }

    private function publicSettingsPayload(): array
    {
        $logoYolu = $this->ayarServisi->al('flutter_logosu');
        $logoVarMi = $logoYolu && Storage::disk('public')->exists($logoYolu);
        $googlePlayDurumu = $this->mobilOdemeAyarServisi->platformDurumu('android');
        $appStoreDurumu = $this->mobilOdemeAyarServisi->platformDurumu('ios');

        return [
            'uygulama_adi' => $this->nullableString('site_adi')
                ?? $this->nullableString('uygulama_adi')
                ?? 'MiniMesaj',
            'uygulama_logosu' => $logoVarMi ? asset('storage/'.$logoYolu) : null,
            'uygulama_versiyonu' => $this->nullableString('uygulama_versiyonu'),
            'mobil_minimum_versiyon' => $this->nullableString('mobil_minimum_versiyon'),
            'varsayilan_dil' => $this->nullableString('varsayilan_dil') ?? 'tr',
            'kayit_aktif_mi' => (bool) $this->ayarServisi->al('kayit_aktif_mi', true),
            'destek_eposta' => $this->nullableString('destek_eposta'),
            'destek_whatsapp' => $this->nullableString('destek_whatsapp'),
            'android_play_store_url' => $this->nullableString('android_play_store_url'),
            'ios_app_store_url' => $this->nullableString('ios_app_store_url'),
            'odeme_kanallari' => [
                'google_play' => $googlePlayDurumu + [
                    'kullanilabilir' => $googlePlayDurumu['aktif'] && $googlePlayDurumu['hazir'],
                ],
                'app_store' => $appStoreDurumu + [
                    'kullanilabilir' => $appStoreDurumu['aktif'] && $appStoreDurumu['hazir'],
                ],
            ],
            'reklamlar' => [
                'aktif_mi' => (bool) $this->ayarServisi->al('admob_aktif_mi', false),
                'test_modu' => (bool) $this->ayarServisi->al('admob_test_modu', true),
                'odul_puani' => max(0, (int) $this->ayarServisi->al('reklam_odulu', 15)),
                'gunluk_odul_limiti' => max(0, (int) $this->ayarServisi->al('reklam_gunluk_odul_limiti', 10)),
                'android' => [
                    'app_id' => $this->nullableString('admob_android_app_id'),
                    'rewarded_unit_id' => $this->nullableString('admob_android_rewarded_unit_id'),
                    'match_native_unit_id' => $this->nullableString('admob_android_match_native_unit_id'),
                ],
                'ios' => [
                    'app_id' => $this->nullableString('admob_ios_app_id'),
                    'rewarded_unit_id' => $this->nullableString('admob_ios_rewarded_unit_id'),
                    'match_native_unit_id' => $this->nullableString('admob_ios_match_native_unit_id'),
                ],
            ],
            'logo_guncelleme_zamani' => $logoVarMi
                ? Storage::disk('public')->lastModified($logoYolu)
                : null,
        ];
    }

    private function todaysUnreadNotificationCount(User $user): int
    {
        return $user->unreadNotifications()
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    private function nullableString(string $anahtar): ?string
    {
        $deger = $this->ayarServisi->al($anahtar);
        if ($deger === null) {
            return null;
        }

        $metin = trim((string) $deger);

        return $metin === '' ? null : $metin;
    }

    private function parseSince(?string $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }

    private function decodeSyncToken(?string $value, CarbonImmutable $fallbackCheckpoint): array
    {
        $normalized = is_string($value) ? trim($value) : '';
        if ($normalized === '') {
            return $this->emptySyncState($fallbackCheckpoint);
        }

        if (str_starts_with($normalized, self::SYNC_CURSOR_PREFIX)) {
            $encoded = substr($normalized, strlen(self::SYNC_CURSOR_PREFIX));
            $decoded = json_decode($this->base64UrlDecode($encoded), true);
            if (is_array($decoded)) {
                return [
                    'since' => $this->nullableCarbon($decoded['since'] ?? null),
                    'checkpoint' => $this->nullableCarbon($decoded['checkpoint'] ?? null) ?? $fallbackCheckpoint,
                    'message_id' => isset($decoded['message_id']) ? (int) $decoded['message_id'] : null,
                    'conversation_updated_at' => $this->nullableCarbon($decoded['conversation_updated_at'] ?? null),
                    'conversation_id' => isset($decoded['conversation_id']) ? (int) $decoded['conversation_id'] : null,
                    'notification_updated_at' => $this->nullableCarbon($decoded['notification_updated_at'] ?? null),
                    'notification_id' => isset($decoded['notification_id']) ? (string) $decoded['notification_id'] : null,
                ];
            }
        }

        try {
            return $this->emptySyncState($fallbackCheckpoint, $this->parseSince($normalized));
        } catch (\Throwable) {
            return $this->emptySyncState($fallbackCheckpoint);
        }
    }

    private function emptySyncState(CarbonImmutable $checkpoint, ?CarbonImmutable $since = null): array
    {
        return [
            'since' => $since,
            'checkpoint' => $checkpoint,
            'message_id' => null,
            'conversation_updated_at' => null,
            'conversation_id' => null,
            'notification_updated_at' => null,
            'notification_id' => null,
        ];
    }

    private function nextSyncState(
        array $state,
        CarbonImmutable $checkpoint,
        $messages,
        $conversations,
        $notifications,
    ): array {
        $next = $state;
        $next['checkpoint'] = $checkpoint;

        if ($messages->isNotEmpty()) {
            $next['message_id'] = (int) $messages->last()->id;
        }

        if ($conversations->isNotEmpty()) {
            $lastConversation = $conversations->last();
            $next['conversation_updated_at'] = CarbonImmutable::parse($lastConversation->updated_at);
            $next['conversation_id'] = (int) $lastConversation->id;
        }

        if ($notifications->isNotEmpty()) {
            $lastNotification = $notifications->last();
            $next['notification_updated_at'] = CarbonImmutable::parse($lastNotification->updated_at);
            $next['notification_id'] = (string) $lastNotification->id;
        }

        return $next;
    }

    private function encodeSyncToken(array $state): string
    {
        $payload = [
            'since' => $state['since'] instanceof CarbonImmutable ? $state['since']->toISOString() : null,
            'checkpoint' => $state['checkpoint'] instanceof CarbonImmutable ? $state['checkpoint']->toISOString() : now()->toISOString(),
            'message_id' => $state['message_id'],
            'conversation_updated_at' => $state['conversation_updated_at'] instanceof CarbonImmutable ? $state['conversation_updated_at']->toISOString() : null,
            'conversation_id' => $state['conversation_id'],
            'notification_updated_at' => $state['notification_updated_at'] instanceof CarbonImmutable ? $state['notification_updated_at']->toISOString() : null,
            'notification_id' => $state['notification_id'],
        ];

        return self::SYNC_CURSOR_PREFIX.$this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private function nullableCarbon(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function syncDateForQuery(CarbonImmutable $value): string
    {
        return $value->setTimezone(config('app.timezone'))->toDateTimeString();
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padded = str_pad($value, strlen($value) + (4 - strlen($value) % 4) % 4, '=');

        return base64_decode(strtr($padded, '-_', '+/')) ?: '';
    }
}
