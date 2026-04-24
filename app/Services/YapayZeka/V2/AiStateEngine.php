<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiConversationState;
use App\Services\YapayZeka\V2\Data\AiConversationStateSnapshot;
use App\Services\YapayZeka\V2\Data\AiInterpretation;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Carbon\CarbonInterface;

class AiStateEngine
{
    public function stateForContext(AiTurnContext $context): AiConversationState
    {
        return AiConversationState::query()->forCounterpart(
            $context->aiUser->id,
            $context->kanal,
            $context->hedefTipi(),
            $context->hedefId(),
        )->firstOrCreate([
            'ai_user_id' => $context->aiUser->id,
            'kanal' => $context->kanal,
            'hedef_tipi' => $context->hedefTipi(),
            'hedef_id' => $context->hedefId(),
        ], [
            'ai_durumu' => AiConversationState::DURUM_IDLE,
            'durum_guncellendi_at' => now(),
        ]);
    }

    public function applyIncoming(
        AiTurnContext $context,
        AiConversationState $state,
        AiInterpretation $interpretation,
    ): AiConversationStateSnapshot {
        $state->fill([
            'samimiyet_puani' => $this->clamp(
                $state->samimiyet_puani + $this->deltaForEmotion($interpretation->emotion),
                -100,
                100,
            ),
            'ilgi_puani' => $this->clamp(
                $state->ilgi_puani + $this->deltaForIntent($interpretation->intent),
                -100,
                100,
            ),
            'guven_puani' => $this->clamp(
                $state->guven_puani + ($interpretation->riskLevel === 'high' ? -8 : 3),
                -100,
                100,
            ),
            'enerji_puani' => $this->clamp(
                $state->enerji_puani + $this->deltaForEnergy($interpretation->energy),
                0,
                100,
            ),
            'ruh_hali' => $this->moodForInterpretation($interpretation),
            'gerilim_seviyesi' => $this->clamp(
                $state->gerilim_seviyesi + ($interpretation->emotion === 'angry' ? 12 : -2),
                0,
                100,
            ),
            'son_konu' => $interpretation->topics[0] ?? $state->son_konu,
            'son_kullanici_duygusu' => $interpretation->emotion,
            'son_ozet' => $interpretation->summary ?: $state->son_ozet,
            'son_mesaj_at' => now(),
        ])->save();

        return $this->snapshot($state);
    }

    public function setRuntimeStatus(
        AiTurnContext $context,
        AiConversationState $state,
        string $status,
        ?CarbonInterface $plannedAt = null,
        ?string $statusText = null,
        array $runtimeMetadata = [],
    ): void {
        $state->forceFill([
            'ai_durumu' => $status,
            'planlanan_cevap_at' => $plannedAt,
            'durum_guncellendi_at' => now(),
            'metadata' => $this->mergeRuntimeMetadata($state, $runtimeMetadata),
        ])->save();

        if ($context->sohbet) {
            $context->sohbet->forceFill([
                'ai_durumu' => $status,
                'ai_durum_metni' => $statusText,
                'ai_planlanan_cevap_at' => $plannedAt,
                'ai_durum_guncellendi_at' => now(),
            ])->save();
        }
    }

    public function markReplyPersisted(
        AiTurnContext $context,
        AiConversationState $state,
        string $replyAim,
        string $replyText,
    ): void {
        $state->forceFill([
            'son_ai_niyeti' => $replyAim,
            'son_ai_mesaj_at' => now(),
            'ai_durumu' => AiConversationState::DURUM_IDLE,
            'durum_guncellendi_at' => now(),
            'planlanan_cevap_at' => null,
            'gerilim_seviyesi' => $this->clamp($state->gerilim_seviyesi - 6, 0, 100),
            'son_ozet' => mb_substr(trim($replyText), 0, 180),
            'metadata' => $this->mergeRuntimeMetadata($state, [
                'reference_message_id' => null,
                'pending_turn_log_id' => null,
            ]),
        ])->save();

        if ($context->sohbet) {
            $context->sohbet->forceFill([
                'ai_durumu' => AiConversationState::DURUM_IDLE,
                'ai_durum_metni' => null,
                'ai_planlanan_cevap_at' => null,
                'ai_durum_guncellendi_at' => now(),
            ])->save();
        }
    }

    public function snapshot(AiConversationState $state): AiConversationStateSnapshot
    {
        return new AiConversationStateSnapshot(
            (int) $state->samimiyet_puani,
            (int) $state->ilgi_puani,
            (int) $state->guven_puani,
            (int) $state->enerji_puani,
            (string) $state->ruh_hali,
            (int) $state->gerilim_seviyesi,
            $state->son_konu,
            $state->son_kullanici_duygusu,
            $state->son_ai_niyeti,
            $state->son_ozet,
            (string) $state->ai_durumu,
        );
    }

    public function isRuntimeTurnCurrent(
        AiConversationState $state,
        ?int $referenceMessageId = null,
        ?int $pendingTurnLogId = null,
    ): bool {
        $runtime = $this->runtimeMetadata($state);

        if (
            $referenceMessageId !== null
            && (int) ($runtime['reference_message_id'] ?? 0) !== $referenceMessageId
        ) {
            return false;
        }

        if (
            $pendingTurnLogId !== null
            && (int) ($runtime['pending_turn_log_id'] ?? 0) !== $pendingTurnLogId
        ) {
            return false;
        }

        return true;
    }

    private function deltaForEmotion(string $emotion): int
    {
        return match ($emotion) {
            'flirty', 'playful' => 6,
            'sad' => 2,
            'angry' => -8,
            default => 3,
        };
    }

    private function deltaForIntent(string $intent): int
    {
        return match ($intent) {
            'flirt', 'greeting', 'opening' => 6,
            'support_seek' => 3,
            'conflict', 'off_platform_push' => -7,
            default => 2,
        };
    }

    private function deltaForEnergy(string $energy): int
    {
        return match ($energy) {
            'high' => 5,
            'low' => -3,
            default => 1,
        };
    }

    private function moodForInterpretation(AiInterpretation $interpretation): string
    {
        return match ($interpretation->emotion) {
            'sad' => 'caring',
            'angry' => 'careful',
            'flirty' => 'playful',
            'playful' => 'energetic',
            default => 'neutral',
        };
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function mergeRuntimeMetadata(AiConversationState $state, array $runtimeMetadata): array
    {
        $metadata = is_array($state->metadata) ? $state->metadata : [];
        if ($runtimeMetadata === []) {
            return $metadata;
        }

        $metadata['runtime'] = array_replace(
            $this->runtimeMetadata($state),
            $runtimeMetadata,
        );

        return $metadata;
    }

    private function runtimeMetadata(AiConversationState $state): array
    {
        $metadata = is_array($state->metadata) ? $state->metadata : [];
        $runtime = $metadata['runtime'] ?? null;

        return is_array($runtime) ? $runtime : [];
    }
}
