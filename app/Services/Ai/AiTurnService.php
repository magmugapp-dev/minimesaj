<?php

namespace App\Services\Ai;

use App\Events\MesajGonderildi;
use App\Events\AiTurnStatusUpdated;
use App\Jobs\SetAiOffline;
use App\Models\AiModerationEvent;
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
use App\Support\MediaMime;
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
        if ($this->isGhostLocked($conversation)) {
            return null;
        }
        if ($this->isFlood($conversation) || $this->isSpam($sourceMessage, $conversation)) {
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
                'period_of_day_for_character' => $this->periodOfDay(data_get($character->character_json, 'schedule.timezone')),
                'period_of_day_for_user' => $this->periodOfDay(config('app.timezone')),
                'season_for_character' => $this->season(data_get($character->character_json, 'schedule.timezone')),
                'season_for_user' => $this->season(config('app.timezone')),
                'day_type_for_character' => $this->dayType(data_get($character->character_json, 'schedule.timezone')),
                'day_type_for_user' => $this->dayType(config('app.timezone')),
                'reengagement_template' => $turn->turn_type === 'proactive'
                    ? $this->reengagementTemplate($character)
                    : null,
                ...$this->minutesUntilOfflineContext($character),
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
                    'file_mime' => MediaMime::forPath($message->dosya_yolu),
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

        $rawText = collect($parts)
            ->map(fn ($part) => $this->sanitizeReplyPart(is_scalar($part) ? (string) $part : null))
            ->filter(fn (?string $part) => $part !== null && trim($part) !== '')
            ->map(fn (string $part) => trim($part))
            ->implode("\n\n");

        $sanitized = AiOutputSanitizer::sanitize($rawText);
        $this->applySystemTags($turn, $conversation, $requestUser, $sanitized->detectedTags);

        $cleanText = $this->applyConversationEndingTag($turn, $conversation, $sanitized->clean);
        if ($sanitized->isEmpty() || trim($cleanText) === '') {
            if (collect($sanitized->detectedTags)->contains(fn (string $tag) => str_starts_with($tag, 'CRISIS_DETECTED'))) {
                $cleanText = 'Bunu tek basima tasiyamam. Lutfen yakindaki birinden ya da bir uzmandan destek al.';
            } elseif ($sanitized->detectedTags !== []) {
                $this->completeWithoutMessages($turn, $conversation);

                return [];
            } else {
                $this->markRetryableFailure($turn, 'empty_ai_reply');

                return [];
            }
        }

        $cleanParts = collect(preg_split('/\n\s*\n+/', $cleanText) ?: [])
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->values();

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
                $turn->retry_after?->toISOString(),
            );
        }

        return $turn;
    }

    public function markPermanentFailure(AiMessageTurn $turn, string $error): AiMessageTurn
    {
        $turn->forceFill([
            'status' => AiMessageTurn::STATUS_DEFERRED,
            'retry_after' => now()->addMinutes(5),
            'last_error' => mb_substr($error, 0, 1000),
        ])->save();

        $conversation = $turn->conversation;
        if ($conversation) {
            $conversation->forceFill([
                'ai_durumu' => 'deferred',
                'ai_durum_metni' => null,
                'ai_planlanan_cevap_at' => $turn->planned_at,
                'ai_durum_guncellendi_at' => now(),
            ])->save();
            AiTurnStatusUpdated::dispatch(
                $conversation->id,
                'deferred',
                null,
                $turn->planned_at?->toISOString(),
                $turn->id,
                $turn->ai_user_id,
                $turn->source_message_id,
                $turn->retry_after?->toISOString(),
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

        $unreadColumn = $this->unreadColumnForAiRecipient($conversation, $aiUser);
        $conversation->forceFill([
            'son_mesaj_id' => $message->id,
            'son_mesaj_gonderen_user_id' => $message->gonderen_user_id,
            'son_mesaj_tarihi' => $message->created_at,
            'son_mesaj_tipi' => $message->mesaj_tipi,
            'son_mesaj_metni' => mb_substr($text, 0, 500),
            'son_mesaj_okundu_mu' => false,
            'toplam_mesaj_sayisi' => DB::raw('toplam_mesaj_sayisi + 1'),
            $unreadColumn => DB::raw($unreadColumn.' + 1'),
        ])->save();

        MesajGonderildi::dispatch($message);
        $recipient = $this->otherParticipant($conversation, $aiUser);
        $recipient?->notify(new YeniMesaj($message, $aiUser));

        return $message->load('gonderen:id,ad,kullanici_adi,profil_resmi,dil');
    }

    private function sanitizeReplyPart(?string $part): ?string
    {
        $sanitized = AiMessageTextSanitizer::sanitize($part);
        if ($sanitized !== null) {
            return $sanitized;
        }

        $raw = trim((string) $part);
        if (preg_match('/^\[(CONV_END:(sleep|work|break|general)|CRISIS_DETECTED|BLOCK_USER:[a-z_]+|GHOST_USER:[a-z_]+)\]/i', $raw) === 1) {
            return $raw;
        }

        return null;
    }

    private function plannedAt(AiCharacter $character, Mesaj $message)
    {
        $conversation = $message->sohbet ?: $message->sohbet()->first();
        $referenceAt = $message->created_at?->copy() ?? now();
        if ($conversation) {
            $closurePlannedAt = $this->plannedAtForClosedConversation($conversation, $character, $referenceAt);
            if ($closurePlannedAt) {
                return $closurePlannedAt;
            }
        }

        $lastAiMessage = Mesaj::query()
            ->where('sohbet_id', $message->sohbet_id)
            ->where('ai_tarafindan_uretildi_mi', true)
            ->where('id', '<', $message->id)
            ->latest('id')
            ->first();

        $responseTime = $lastAiMessage
            ? abs($message->created_at->diffInSeconds($lastAiMessage->created_at))
            : 3600;

        $delay = $this->gapBaseSeconds($responseTime) * $this->messageLengthMultiplier($message);
        $jitter = random_int(85, 115) / 100;
        $delaySeconds = max(2, (int) round($delay * $jitter));
        $plannedAt = $referenceAt->copy()->addSeconds($delaySeconds);

        return $this->moveOutOfSleepWindow($plannedAt, $character);
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

    private function unreadColumnForAiRecipient(Sohbet $conversation, User $sender): string
    {
        $match = $conversation->eslesme()->first();
        if (!$match) {
            return 'user_okunmamis_sayisi';
        }

        $recipientId = (int) $match->user_id === (int) $sender->id
            ? (int) $match->eslesen_user_id
            : (int) $match->user_id;

        return (int) $match->user_id === $recipientId
            ? 'user_okunmamis_sayisi'
            : 'eslesen_okunmamis_sayisi';
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

    private function gapBaseSeconds(int $responseTime): int
    {
        return match (true) {
            $responseTime < 60 => 10,
            $responseTime < 300 => 35,
            $responseTime < 1200 => 90,
            $responseTime < 7200 => 300,
            $responseTime < 28800 => 900,
            $responseTime < 86400 => 1800,
            default => 3600,
        };
    }

    private function messageLengthMultiplier(Mesaj $message): float
    {
        $length = mb_strlen(trim((string) $message->mesaj_metni));

        return match (true) {
            $length < 10 => 0.3,
            $length <= 30 => 0.5,
            $length <= 80 => 0.8,
            $length <= 150 => 1.2,
            default => 1.5,
        };
    }

    private function plannedAtForClosedConversation(Sohbet $conversation, AiCharacter $character, $referenceAt)
    {
        $closedAt = $conversation->ai_konusma_kapanisi_at;
        $category = $conversation->ai_kapanis_kategorisi;
        if (!$closedAt || !$category) {
            return null;
        }

        if ($referenceAt->lessThan($closedAt->copy()->addMinutes(15))) {
            $conversation->forceFill([
                'ai_konusma_kapanisi_at' => null,
                'ai_kapanis_kategorisi' => null,
            ])->save();

            return null;
        }

        $target = match ($category) {
            'sleep' => $this->nextWakeAt($closedAt, $character),
            'work' => $closedAt->copy()->addHours(random_int(3, 6)),
            'break' => $closedAt->copy()->addMinutes(random_int(30, 90)),
            default => $closedAt->copy()->addHours(random_int(1, 3)),
        };

        if ($target && $referenceAt->lessThan($target)) {
            return $target;
        }

        $conversation->forceFill([
            'ai_konusma_kapanisi_at' => null,
            'ai_kapanis_kategorisi' => null,
        ])->save();

        return null;
    }

    private function moveOutOfSleepWindow($plannedAt, AiCharacter $character)
    {
        return $this->isAsleepAt($plannedAt, $character)
            ? $this->nextWakeAt($plannedAt, $character)
            : $plannedAt;
    }

    private function isAsleepAt($moment, AiCharacter $character): bool
    {
        $window = $this->sleepWindow($moment, $character);
        if (!$window) {
            return false;
        }

        return $window['asleep'];
    }

    private function nextWakeAt($moment, AiCharacter $character)
    {
        $window = $this->sleepWindow($moment, $character);
        if (!$window) {
            return $moment->copy()->addMinutes(random_int(5, 30));
        }

        return $window['wake_at']->copy()->addMinutes(random_int(5, 30));
    }

    private function sleepWindow($moment, AiCharacter $character): ?array
    {
        $timezone = data_get($character->character_json, 'schedule.timezone', config('app.timezone'));
        $local = $moment->copy()->timezone($timezone);
        $weekend = in_array((int) $local->dayOfWeekIso, [6, 7], true);
        $prefix = $weekend ? 'weekend' : 'weekday';
        $start = data_get($character->character_json, "schedule.{$prefix}.sleep_start")
            ?? data_get($character->character_json, "schedule.{$prefix}_sleep_start")
            ?? data_get($character->character_json, 'schedule.sleep_start');
        $end = data_get($character->character_json, "schedule.{$prefix}.sleep_end")
            ?? data_get($character->character_json, "schedule.{$prefix}_sleep_end")
            ?? data_get($character->character_json, 'schedule.sleep_end');

        if (!$this->validTime($start) || !$this->validTime($end)) {
            return null;
        }

        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));
        $currentMinutes = ((int) $local->format('H')) * 60 + (int) $local->format('i');
        $startMinutes = $startHour * 60 + $startMinute;
        $endMinutes = $endHour * 60 + $endMinute;
        if ($startMinutes === $endMinutes) {
            return null;
        }

        $overnight = $endMinutes <= $startMinutes;
        $asleep = $overnight
            ? ($currentMinutes >= $startMinutes || $currentMinutes < $endMinutes)
            : ($currentMinutes >= $startMinutes && $currentMinutes < $endMinutes);
        $wakeAt = $local->copy()->setTime($endHour, $endMinute);
        if ($overnight && $currentMinutes >= $startMinutes) {
            $wakeAt->addDay();
        }
        $sleepStartAt = $local->copy()->setTime($startHour, $startMinute);
        if (!$asleep) {
            if ($overnight) {
                if ($currentMinutes >= $endMinutes && $currentMinutes >= $startMinutes) {
                    $sleepStartAt->addDay();
                }
            } elseif ($currentMinutes >= $endMinutes) {
                $sleepStartAt->addDay();
            }
        }

        return [
            'asleep' => $asleep,
            'sleep_start_at' => $sleepStartAt->timezone(config('app.timezone')),
            'wake_at' => $wakeAt->timezone(config('app.timezone')),
        ];
    }

    private function validTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{1,2}:\d{2}$/', $value) === 1;
    }

    private function applyConversationEndingTag(AiMessageTurn $turn, Sohbet $conversation, string $text): string
    {
        if (preg_match('/\[CONV_END:(sleep|work|break|general)\]/i', $text, $match) !== 1) {
            return $text;
        }

        $category = strtolower($match[1]);
        $conversation->forceFill([
            'ai_konusma_kapanisi_at' => now(),
            'ai_kapanis_kategorisi' => $category,
        ])->save();

        if (in_array($category, ['sleep', 'work', 'general'], true)) {
            SetAiOffline::dispatch((int) $turn->ai_user_id)->delay(now()->addMinute());
        }

        return trim(preg_replace('/\[CONV_END:(sleep|work|break|general)\]/i', '', $text) ?? $text);
    }

    private function applySystemTags(AiMessageTurn $turn, Sohbet $conversation, User $requestUser, array $tags): void
    {
        foreach ($tags as $tag) {
            if (str_starts_with($tag, 'BLOCK_USER:')) {
                $category = strtolower(substr($tag, strlen('BLOCK_USER:')));
                Engelleme::query()->firstOrCreate([
                    'engelleyen_user_id' => $turn->ai_user_id,
                    'engellenen_user_id' => $requestUser->id,
                ], ['sebep' => $category]);
                $conversation->forceFill(['durum' => 'kapali'])->save();
                $this->recordModerationEvent($turn, $requestUser, 'block', $category);
            }

            if (str_starts_with($tag, 'GHOST_USER:')) {
                $type = strtolower(substr($tag, strlen('GHOST_USER:')));
                $this->applyGhostLockout($turn, $conversation, $requestUser, $type);
            }
        }
    }

    private function applyGhostLockout(AiMessageTurn $turn, Sohbet $conversation, User $requestUser, string $type): void
    {
        $dominance = $this->dominanceFor($turn->aiUser->aiCharacter);
        if ($type === 'narrative' && !$this->hasPreviousSilentGhost($turn, $requestUser)) {
            $type = 'silent';
        }

        $lockoutUntil = $type === 'narrative'
            ? now()->addYears(20)
            : now()->addHours(match ($dominance) {
                'dominant' => 96,
                'passive' => 24,
                default => 48,
            });

        $conversation->forceFill([
            'ai_ghost_lockout_until' => $lockoutUntil,
            'ai_ghost_tipi' => $type,
        ])->save();
        $this->recordModerationEvent($turn, $requestUser, "ghost_{$type}", $dominance, $lockoutUntil);
    }

    private function completeWithoutMessages(AiMessageTurn $turn, Sohbet $conversation): void
    {
        $turn->forceFill([
            'status' => AiMessageTurn::STATUS_COMPLETED,
            'completed_at' => now(),
            'delivered_message_ids' => [],
            'last_error' => null,
        ])->save();
        $this->clearConversationTyping($conversation, $turn);
    }

    private function recordModerationEvent(
        AiMessageTurn $turn,
        User $requestUser,
        string $eventType,
        ?string $dominanceOrReason = null,
        $lockoutUntil = null,
    ): void {
        AiModerationEvent::query()->create([
            'ai_user_id' => $turn->ai_user_id,
            'user_id' => $requestUser->id,
            'conversation_id' => $turn->conversation_id,
            'event_type' => $eventType,
            'dominance' => in_array($dominanceOrReason, ['passive', 'balanced', 'dominant'], true) ? $dominanceOrReason : null,
            'lockout_until' => $lockoutUntil,
            'metadata' => $dominanceOrReason && !in_array($dominanceOrReason, ['passive', 'balanced', 'dominant'], true)
                ? ['reason' => $dominanceOrReason]
                : null,
        ]);
    }

    private function hasPreviousSilentGhost(AiMessageTurn $turn, User $requestUser): bool
    {
        return AiModerationEvent::query()
            ->where('ai_user_id', $turn->ai_user_id)
            ->where('user_id', $requestUser->id)
            ->where('event_type', 'ghost_silent')
            ->exists();
    }

    private function dominanceFor(?AiCharacter $character): string
    {
        $dominance = strtolower((string) (
            data_get($character?->character_json, 'personality.dominance')
            ?? data_get($character?->character_json, 'interaction.dominance')
            ?? 'balanced'
        ));

        return in_array($dominance, ['passive', 'balanced', 'dominant'], true)
            ? $dominance
            : 'balanced';
    }

    private function isGhostLocked(Sohbet $conversation): bool
    {
        return $conversation->ai_ghost_lockout_until
            && now()->lessThan($conversation->ai_ghost_lockout_until);
    }

    private function isFlood(Sohbet $conversation): bool
    {
        return $conversation->mesajlar()
            ->where('ai_tarafindan_uretildi_mi', false)
            ->where('created_at', '>=', now()->subMinute())
            ->count() >= 5;
    }

    private function isSpam(Mesaj $sourceMessage, Sohbet $conversation): bool
    {
        $text = trim((string) $sourceMessage->mesaj_metni);
        if ($text === '') {
            return false;
        }

        $recent = $conversation->mesajlar()
            ->where('ai_tarafindan_uretildi_mi', false)
            ->latest('id')
            ->limit(5)
            ->pluck('mesaj_metni')
            ->map(fn ($value) => trim((string) $value));

        return $recent->filter(fn (string $value) => $value !== '' && $value === $text)->count() >= 4;
    }

    private function periodOfDay(?string $timezone): string
    {
        $hour = (int) now($timezone ?: config('app.timezone'))->format('G');

        return match (true) {
            $hour >= 5 && $hour <= 11 => 'morning',
            $hour >= 12 && $hour <= 17 => 'afternoon',
            $hour >= 18 && $hour <= 22 => 'evening',
            default => 'late_night',
        };
    }

    private function season(?string $timezone): string
    {
        $month = (int) now($timezone ?: config('app.timezone'))->format('n');

        return match (true) {
            $month >= 3 && $month <= 5 => 'spring',
            $month >= 6 && $month <= 8 => 'summer',
            $month >= 9 && $month <= 11 => 'autumn',
            default => 'winter',
        };
    }

    private function dayType(?string $timezone): string
    {
        return now($timezone ?: config('app.timezone'))->isWeekend() ? 'weekend' : 'weekday';
    }

    private function reengagementTemplate(AiCharacter $character): ?string
    {
        $templates = $character->reengagement_templates;
        if (!is_array($templates) || $templates === []) {
            return null;
        }

        $values = collect($templates)
            ->filter(fn ($template) => is_string($template) && trim($template) !== '')
            ->values();

        return $values->isNotEmpty() ? $values->random() : null;
    }

    private function minutesUntilOfflineContext(AiCharacter $character): array
    {
        $window = $this->sleepWindow(now(), $character);
        if (!$window || $window['asleep']) {
            return [];
        }

        $minutes = now()->diffInMinutes($window['sleep_start_at'], false);
        if ($minutes < 0 || $minutes > 15) {
            return [];
        }

        return ['minutes_until_offline' => $minutes];
    }

    private function defaultViolationThreshold(string $category): int
    {
        return in_array($category, ['absolute', 'underage', 'violence'], true) ? 1 : 3;
    }
}
