<?php

namespace App\Services\Ai;

use App\Events\MesajGonderildi;
use App\Events\AiTurnStatusUpdated;
use App\Models\AiBlockThreshold;
use App\Models\AiCharacter;
use App\Models\AiMessageTurn;
use App\Models\AiPromptVersion;
use App\Models\AiViolationCounter;
use App\Models\Engelleme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Notifications\YeniMesaj;
use App\Support\AiMessageTextSanitizer;
use App\Support\Language;
use App\Support\MediaUrl;
use App\Support\MessageMediaUrl;
use Illuminate\Support\Facades\DB;

class AiTurnService
{
    public function createReplyTurn(Sohbet $conversation, Mesaj $sourceMessage, User $aiUser): ?AiMessageTurn
    {
        $character = $aiUser->aiCharacter()->first();
        if (!$character?->active) {
            return null;
        }

        $plannedAt = $this->plannedAt($character, $sourceMessage);
        $turn = AiMessageTurn::query()->updateOrCreate(
            [
                'idempotency_key' => $this->idempotencyKey($conversation, $sourceMessage, $aiUser),
            ],
            [
                'conversation_id' => $conversation->id,
                'ai_user_id' => $aiUser->id,
                'source_message_id' => $sourceMessage->id,
                'turn_type' => 'reply',
                'status' => AiMessageTurn::STATUS_PENDING,
                'planned_at' => $plannedAt,
                'retry_after' => null,
                'attempt_count' => 0,
                'max_attempts' => 5,
                'last_error' => null,
            ],
        );

        $conversation->forceFill([
            'ai_durumu' => 'pending',
            'ai_durum_metni' => null,
            'ai_planlanan_cevap_at' => $plannedAt,
            'ai_durum_guncellendi_at' => now(),
        ])->save();
        AiTurnStatusUpdated::dispatch(
            $conversation->id,
            'pending',
            null,
            $plannedAt?->toISOString(),
            $turn->id,
            $turn->ai_user_id,
            $turn->source_message_id,
        );

        return $turn;
    }

    public function pendingTurnsFor(User $user, int $lookaheadSeconds = 0)
    {
        $plannedBefore = now()->addSeconds(max(0, min(300, $lookaheadSeconds)));
        $conversationIds = Sohbet::query()
            ->whereHas('eslesme', function ($query) use ($user): void {
                $query->where('user_id', $user->id)->orWhere('eslesen_user_id', $user->id);
            })
            ->pluck('id');

        return AiMessageTurn::query()
            ->with(['aiUser.aiCharacter', 'sourceMessage'])
            ->whereIn('conversation_id', $conversationIds)
            ->whereIn('status', [AiMessageTurn::STATUS_PENDING, AiMessageTurn::STATUS_DEFERRED])
            ->where(function ($query) use ($plannedBefore): void {
                $query->whereNull('planned_at')->orWhere('planned_at', '<=', $plannedBefore);
            })
            ->where(function ($query): void {
                $query->whereNull('retry_after')->orWhere('retry_after', '<=', now());
            })
            ->oldest('planned_at')
            ->limit(20)
            ->get();
    }

    public function contextForTurn(AiMessageTurn $turn, User $requestUser): array
    {
        $conversation = $turn->conversation()->with([
            'eslesme.user',
            'eslesme.eslesenUser',
            'mesajlar' => fn ($query) => $query->with('gonderen')->latest()->limit(30),
        ])->firstOrFail();

        $this->abortUnlessParticipant($conversation, $requestUser);

        $character = $turn->aiUser->aiCharacter()->firstOrFail();
        $prompt = AiPromptVersion::query()->where('active', true)->latest('id')->first();

        return [
            'turn' => $turn->only(['id', 'status', 'turn_type', 'attempt_count', 'max_attempts', 'planned_at', 'retry_after']),
            'conversation_id' => $conversation->id,
            'source_message_id' => $turn->source_message_id,
            'ai_user_id' => $turn->ai_user_id,
            'character' => $character->character_json,
            'model_config' => [
                'model_name' => $character->model_name,
                'temperature' => (float) $character->temperature,
                'top_p' => (float) $character->top_p,
                'max_output_tokens' => (int) $character->max_output_tokens,
            ],
            'global_prompt' => $prompt ? [
                'version' => $prompt->version,
                'hash' => $prompt->hash,
                'prompt_xml' => $prompt->prompt_xml,
            ] : null,
            'runtime_context' => [
                'user_language' => Language::normalizeCode($requestUser->dil) ?: 'tr',
                'relationship_stage' => $this->relationshipStage($conversation),
                'user_timezone_offset' => 3,
                'character_timezone' => data_get($character->character_json, 'schedule.timezone', config('app.timezone')),
            ],
            'messages' => $conversation->mesajlar
                ->sortBy('id')
                ->map(fn (Mesaj $message) => [
                    'id' => $message->id,
                    'sender_id' => $message->gonderen_user_id,
                    'is_ai' => (bool) $message->ai_tarafindan_uretildi_mi,
                    'type' => $message->mesaj_tipi,
                    'text' => $message->mesaj_metni,
                    'file_url' => MessageMediaUrl::forMessage($message)
                        ?? MediaUrl::resolve($message->dosya_yolu)
                        ?? MediaUrl::buildUrl($message->dosya_yolu),
                    'file_mime' => $this->messageFileMime($message),
                    'file_duration' => $message->dosya_suresi,
                    'created_at' => $message->created_at?->toISOString(),
                ])
                ->values(),
        ];
    }

    public function persistReply(AiMessageTurn $turn, User $requestUser, array $parts, ?string $clientMessageId = null): array
    {
        $conversation = $turn->conversation()->with('eslesme')->firstOrFail();
        $this->abortUnlessParticipant($conversation, $requestUser);

        if ($turn->status === AiMessageTurn::STATUS_COMPLETED && is_array($turn->delivered_message_ids)) {
            return Mesaj::query()
                ->whereIn('id', $turn->delivered_message_ids)
                ->with('gonderen:id,ad,kullanici_adi,profil_resmi,dil')
                ->orderBy('id')
                ->get()
                ->all();
        }

        if ($this->hasNewerIncoming($turn)) {
            $turn->forceFill([
                'status' => AiMessageTurn::STATUS_CANCELLED,
                'completed_at' => now(),
                'last_error' => 'newer_incoming_message',
            ])->save();
            $this->clearConversationTyping($conversation, $turn);

            return [];
        }

        $cleanParts = collect($parts)
            ->map(fn ($part) => AiMessageTextSanitizer::sanitize(is_scalar($part) ? (string) $part : null))
            ->filter(fn (?string $part) => $part !== null && trim($part) !== '')
            ->map(fn (string $part) => trim($part))
            ->values();

        if ($cleanParts->isEmpty()) {
            $this->markRetryableFailure($turn, 'empty_ai_reply');

            return [];
        }

        return DB::transaction(function () use ($turn, $conversation, $cleanParts, $clientMessageId): array {
            $messages = [];
            foreach ($cleanParts as $index => $part) {
                $messages[] = $this->createAiMessage(
                    $conversation,
                    $turn->aiUser,
                    $part,
                    $clientMessageId ? $clientMessageId.'-'.$index : "ai-turn-{$turn->id}-{$index}",
                );
            }

            $turn->forceFill([
                'status' => AiMessageTurn::STATUS_COMPLETED,
                'completed_at' => now(),
                'delivered_message_ids' => collect($messages)->pluck('id')->all(),
                'last_error' => null,
            ])->save();

            $conversation->forceFill([
                'ai_durumu' => null,
                'ai_durum_metni' => null,
                'ai_planlanan_cevap_at' => null,
                'ai_durum_guncellendi_at' => now(),
            ])->save();
            $this->broadcastClearedStatus($conversation, $turn);

            return $messages;
        });
    }

    public function markClientFailure(AiMessageTurn $turn, User $requestUser, string $error): AiMessageTurn
    {
        $conversation = $turn->conversation()->with('eslesme')->firstOrFail();
        $this->abortUnlessParticipant($conversation, $requestUser);

        if ($turn->status === AiMessageTurn::STATUS_COMPLETED || $turn->status === AiMessageTurn::STATUS_CANCELLED) {
            return $turn;
        }

        return $this->markRetryableFailure($turn, $error);
    }

    public function markRetryableFailure(AiMessageTurn $turn, string $error): AiMessageTurn
    {
        $attempts = min((int) $turn->max_attempts, (int) $turn->attempt_count + 1);
        $deferred = $attempts >= (int) $turn->max_attempts;

        $turn->forceFill([
            'status' => $deferred ? AiMessageTurn::STATUS_DEFERRED : AiMessageTurn::STATUS_PENDING,
            'attempt_count' => $attempts,
            'retry_after' => $deferred ? now()->addMinutes(5) : now()->addSeconds($this->backoffSeconds($attempts)),
            'last_error' => mb_substr($error, 0, 1000),
        ])->save();
        $conversation = $turn->conversation;
        if ($conversation) {
            $conversation->forceFill([
                'ai_durumu' => $deferred ? 'deferred' : 'pending',
                'ai_durum_metni' => null,
                'ai_planlanan_cevap_at' => $turn->planned_at,
                'ai_durum_guncellendi_at' => now(),
            ])->save();
            AiTurnStatusUpdated::dispatch(
                $conversation->id,
                $deferred ? 'deferred' : 'pending',
                null,
                $turn->planned_at?->toISOString(),
                $turn->id,
                $turn->ai_user_id,
                $turn->source_message_id,
            );
        }

        return $turn;
    }

    public function markProcessing(AiMessageTurn $turn): AiMessageTurn
    {
        $turn->forceFill([
            'status' => AiMessageTurn::STATUS_PROCESSING,
            'started_at' => now(),
            'last_error' => null,
        ])->save();

        $conversation = $turn->conversation;
        if ($conversation) {
            $conversation->forceFill([
                'ai_durumu' => 'typing',
                'ai_durum_metni' => 'Yaziyor...',
                'ai_planlanan_cevap_at' => $turn->planned_at,
                'ai_durum_guncellendi_at' => now(),
            ])->save();
            AiTurnStatusUpdated::dispatch(
                $conversation->id,
                'typing',
                'Yaziyor...',
                $turn->planned_at?->toISOString(),
                $turn->id,
                $turn->ai_user_id,
                $turn->source_message_id,
            );
        }

        return $turn;
    }

    private function clearConversationTyping(Sohbet $conversation, AiMessageTurn $turn): void
    {
        $conversation->forceFill([
            'ai_durumu' => null,
            'ai_durum_metni' => null,
            'ai_planlanan_cevap_at' => null,
            'ai_durum_guncellendi_at' => now(),
        ])->save();

        $this->broadcastClearedStatus($conversation, $turn);
    }

    private function broadcastClearedStatus(Sohbet $conversation, AiMessageTurn $turn): void
    {
        AiTurnStatusUpdated::dispatch(
            $conversation->id,
            '',
            null,
            null,
            $turn->id,
            $turn->ai_user_id,
            $turn->source_message_id,
        );
    }

    public function recordViolation(User $user, User $aiUser, string $category): array
    {
        $threshold = AiBlockThreshold::query()
            ->where('category', $category)
            ->where('active', true)
            ->value('threshold') ?? $this->defaultViolationThreshold($category);

        $counter = AiViolationCounter::query()->firstOrNew([
            'ai_user_id' => $aiUser->id,
            'user_id' => $user->id,
            'category' => $category,
        ]);
        $counter->count = (int) $counter->count + 1;
        $counter->last_violation_at = now();

        if ($counter->count >= $threshold) {
            Engelleme::query()->firstOrCreate([
                'engelleyen_user_id' => $aiUser->id,
                'engellenen_user_id' => $user->id,
            ]);
            $counter->blocked = true;
        }

        $counter->save();

        return [
            'count' => $counter->count,
            'threshold' => (int) $threshold,
            'blocked' => (bool) $counter->blocked,
        ];
    }

    private function createAiMessage(Sohbet $conversation, User $aiUser, string $text, string $clientMessageId): Mesaj
    {
        $existing = Mesaj::query()
            ->where('sohbet_id', $conversation->id)
            ->where('gonderen_user_id', $aiUser->id)
            ->where('client_message_id', $clientMessageId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $character = $aiUser->aiCharacter;
        $languageCode = data_get($character?->character_json, 'languages.primary_language_code')
            ?: Language::normalizeCode($aiUser->dil)
            ?: 'tr';

        $message = Mesaj::query()->create([
            'sohbet_id' => $conversation->id,
            'gonderen_user_id' => $aiUser->id,
            'mesaj_tipi' => 'metin',
            'mesaj_metni' => $text,
            'dil_kodu' => $languageCode,
            'dil_adi' => data_get($character?->character_json, 'languages.primary_language_name') ?: Language::name($languageCode),
            'ai_tarafindan_uretildi_mi' => true,
            'client_message_id' => $clientMessageId,
        ]);

        $conversation->forceFill([
            'son_mesaj_id' => $message->id,
            'son_mesaj_tarihi' => $message->created_at,
            'toplam_mesaj_sayisi' => DB::raw('toplam_mesaj_sayisi + 1'),
        ])->save();

        MesajGonderildi::dispatch($message);
        $recipient = $this->otherParticipant($conversation, $aiUser);
        $recipient?->notify(new YeniMesaj($message, $aiUser));

        return $message->load('gonderen:id,ad,kullanici_adi,profil_resmi,dil');
    }

    private function plannedAt(AiCharacter $character, Mesaj $message)
    {
        $limits = data_get($character->character_json, 'rate_limits', []);
        $min = max(0, (int) ($limits['min_response_seconds'] ?? 3));
        $max = max($min, (int) ($limits['max_response_seconds'] ?? 30));
        $seed = abs((int) crc32($message->id.'|'.$character->character_id));
        $delay = $min + ($max > $min ? $seed % (($max - $min) + 1) : 0);

        return $message->created_at?->copy()->addSeconds($delay) ?? now()->addSeconds($delay);
    }

    private function idempotencyKey(Sohbet $conversation, Mesaj $sourceMessage, User $aiUser): string
    {
        return "reply:{$conversation->id}:{$sourceMessage->id}:{$aiUser->id}";
    }

    private function hasNewerIncoming(AiMessageTurn $turn): bool
    {
        if (!$turn->source_message_id) {
            return false;
        }

        return Mesaj::query()
            ->where('sohbet_id', $turn->conversation_id)
            ->where('gonderen_user_id', '!=', $turn->ai_user_id)
            ->where('id', '>', $turn->source_message_id)
            ->exists();
    }

    private function relationshipStage(Sohbet $conversation): string
    {
        $count = (int) $conversation->mesajlar()->count();

        return match (true) {
            $count >= 80 => 'close',
            $count >= 30 => 'friend',
            $count >= 10 => 'warming',
            default => 'new',
        };
    }

    private function otherParticipant(Sohbet $conversation, User $sender): ?User
    {
        $match = $conversation->eslesme()->with(['user', 'eslesenUser'])->first();
        if (!$match) {
            return null;
        }

        return (int) $match->user_id === (int) $sender->id
            ? $match->eslesenUser
            : $match->user;
    }

    private function abortUnlessParticipant(Sohbet $conversation, User $user): void
    {
        abort_unless(
            $conversation->eslesme()
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id)->orWhere('eslesen_user_id', $user->id);
                })
                ->exists(),
            403,
        );
    }

    private function backoffSeconds(int $attempt): int
    {
        return min(60, (2 ** max(0, $attempt - 1)) + random_int(0, 3));
    }

    private function messageFileMime(Mesaj $message): ?string
    {
        $path = trim((string) $message->dosya_yolu);
        if ($path === '') {
            return null;
        }

        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'm4a', 'mp4' => 'audio/mp4',
            'aac' => 'audio/aac',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            default => null,
        };
    }

    private function defaultViolationThreshold(string $category): int
    {
        return in_array($category, ['absolute', 'underage', 'violence'], true) ? 1 : 3;
    }
}
