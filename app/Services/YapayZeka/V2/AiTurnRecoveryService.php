<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiConversationState;
use App\Models\AiTurnLog;
use App\Models\InstagramAiGorevi;
use App\Models\Sohbet;
use App\Models\YapayZekaGorevi;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AiTurnRecoveryService
{
    public function recover(
        ?CarbonInterface $now = null,
        int $processingStaleMinutes = 10,
        int $typingGraceSeconds = 90,
    ): array {
        $now = $now ? Carbon::instance($now) : now();
        $summary = [
            'turn_logs' => 0,
            'legacy_tasks' => 0,
            'conversation_states' => 0,
            'sohbets' => 0,
        ];

        foreach ($this->staleTurnLogs($now, $processingStaleMinutes, $typingGraceSeconds) as $turnLog) {
            $reason = $this->reasonFor($turnLog, $now, $processingStaleMinutes, $typingGraceSeconds);
            $this->failTurnLog($turnLog, $reason, $now);
            $summary['turn_logs']++;

            $summary['legacy_tasks'] += $this->failLegacyTask($turnLog, $reason, $now);

            $cleared = $this->clearRuntimeState($turnLog, $now);
            $summary['conversation_states'] += $cleared['conversation_states'];
            $summary['sohbets'] += $cleared['sohbets'];
        }

        $legacyOnly = $this->recoverLegacyTasksWithoutOpenTurn($now, $processingStaleMinutes);
        $summary['legacy_tasks'] += $legacyOnly['legacy_tasks'];
        $summary['conversation_states'] += $legacyOnly['conversation_states'];
        $summary['sohbets'] += $legacyOnly['sohbets'];

        return $summary;
    }

    private function staleTurnLogs(
        CarbonInterface $now,
        int $processingStaleMinutes,
        int $typingGraceSeconds,
    ): iterable {
        $processingCutoff = Carbon::instance($now)->subMinutes($processingStaleMinutes);
        $typingCutoff = Carbon::instance($now)->subSeconds($typingGraceSeconds);

        $processingLogs = AiTurnLog::query()
            ->whereIn('durum', ['processing', 'generated'])
            ->where('updated_at', '<=', $processingCutoff)
            ->orderBy('id')
            ->get();

        $typingLogs = AiTurnLog::query()
            ->where('durum', 'typing')
            ->where('updated_at', '<=', $typingCutoff)
            ->orderBy('id')
            ->get()
            ->filter(fn (AiTurnLog $turnLog) => $this->typingDueAt($turnLog)?->lessThanOrEqualTo($typingCutoff) ?? true);

        return $processingLogs->merge($typingLogs);
    }

    private function reasonFor(
        AiTurnLog $turnLog,
        CarbonInterface $now,
        int $processingStaleMinutes,
        int $typingGraceSeconds,
    ): string {
        if ($turnLog->durum === 'typing') {
            return 'AI cevabi typing asamasinda teslim edilemeden takildi. Teslim grace suresi: '
                . $typingGraceSeconds . ' saniye. Kontrol zamani: ' . Carbon::instance($now)->toISOString();
        }

        return 'AI turn processing asamasinda takildi. Esik: '
            . $processingStaleMinutes . ' dakika. Kontrol zamani: ' . Carbon::instance($now)->toISOString();
    }

    private function failTurnLog(AiTurnLog $turnLog, string $reason, CarbonInterface $now): void
    {
        $turnLog->forceFill([
            'durum' => 'failed',
            'degerlendirme' => [
                'accepted' => false,
                'reasons' => ['stale_turn_recovered'],
                'error' => $reason,
            ],
            'tamamlandi_at' => $now,
            'metadata' => $this->mergedMetadata($turnLog, [
                'recovered_at' => Carbon::instance($now)->toISOString(),
                'recovery_reason' => $reason,
            ]),
        ])->save();
    }

    private function failLegacyTask(AiTurnLog $turnLog, string $reason, CarbonInterface $now): int
    {
        $values = [
            'durum' => 'basarisiz',
            'hata_mesaji' => mb_substr($reason, 0, 1000),
            'tamamlandi_at' => $now,
        ];

        if ($turnLog->kanal === 'instagram' && $turnLog->instagram_mesaj_id) {
            return InstagramAiGorevi::query()
                ->where('instagram_mesaj_id', $turnLog->instagram_mesaj_id)
                ->whereIn('durum', ['bekliyor', 'istek_gonderildi'])
                ->update($values);
        }

        if ($turnLog->gelen_mesaj_id && $turnLog->ai_user_id) {
            return YapayZekaGorevi::query()
                ->where('gelen_mesaj_id', $turnLog->gelen_mesaj_id)
                ->where('ai_user_id', $turnLog->ai_user_id)
                ->whereIn('durum', ['bekliyor', 'istek_gonderildi'])
                ->update($values);
        }

        return 0;
    }

    private function recoverLegacyTasksWithoutOpenTurn(CarbonInterface $now, int $processingStaleMinutes): array
    {
        $summary = ['legacy_tasks' => 0, 'conversation_states' => 0, 'sohbets' => 0];
        $cutoff = Carbon::instance($now)->subMinutes($processingStaleMinutes);
        $reason = 'AI legacy gorevi aktif turn log olmadan takildi. Esik: '
            . $processingStaleMinutes . ' dakika. Kontrol zamani: ' . Carbon::instance($now)->toISOString();

        $datingTasks = YapayZekaGorevi::query()
            ->whereIn('durum', ['bekliyor', 'istek_gonderildi'])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        foreach ($datingTasks as $task) {
            if ($this->hasOpenDatingTurnLog($task)) {
                continue;
            }

            $task->forceFill([
                'durum' => 'basarisiz',
                'hata_mesaji' => mb_substr($reason, 0, 1000),
                'tamamlandi_at' => $now,
            ])->save();
            $summary['legacy_tasks']++;

            $cleared = $this->clearRuntimeStateForDatingTask($task, $now);
            $summary['conversation_states'] += $cleared['conversation_states'];
            $summary['sohbets'] += $cleared['sohbets'];
        }

        $instagramTasks = InstagramAiGorevi::query()
            ->whereIn('durum', ['bekliyor', 'istek_gonderildi'])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        foreach ($instagramTasks as $task) {
            if ($this->hasOpenInstagramTurnLog($task)) {
                continue;
            }

            $task->forceFill([
                'durum' => 'basarisiz',
                'hata_mesaji' => mb_substr($reason, 0, 1000),
                'tamamlandi_at' => $now,
            ])->save();
            $summary['legacy_tasks']++;
        }

        return $summary;
    }

    private function hasOpenDatingTurnLog(YapayZekaGorevi $task): bool
    {
        return AiTurnLog::query()
            ->where('kanal', 'dating')
            ->where('ai_user_id', $task->ai_user_id)
            ->where('gelen_mesaj_id', $task->gelen_mesaj_id)
            ->whereIn('durum', ['processing', 'generated', 'typing'])
            ->exists();
    }

    private function hasOpenInstagramTurnLog(InstagramAiGorevi $task): bool
    {
        return AiTurnLog::query()
            ->where('kanal', 'instagram')
            ->where('instagram_mesaj_id', $task->instagram_mesaj_id)
            ->whereIn('durum', ['processing', 'generated', 'typing'])
            ->exists();
    }

    private function clearRuntimeStateForDatingTask(YapayZekaGorevi $task, CarbonInterface $now): array
    {
        $summary = ['conversation_states' => 0, 'sohbets' => 0];
        $task->loadMissing('sohbet.eslesme');

        if (!$task->sohbet?->eslesme || !$task->ai_user_id) {
            return $summary;
        }

        $match = $task->sohbet->eslesme;
        $targetUserId = (int) $match->user_id === (int) $task->ai_user_id
            ? (int) $match->eslesen_user_id
            : (int) $match->user_id;

        $state = AiConversationState::query()
            ->forCounterpart((int) $task->ai_user_id, 'dating', 'user', $targetUserId)
            ->first();

        if (!$state || !$this->stateBelongsToReference($state, $task->gelen_mesaj_id)) {
            return $summary;
        }

        $state->forceFill([
            'ai_durumu' => AiConversationState::DURUM_IDLE,
            'planlanan_cevap_at' => null,
            'durum_guncellendi_at' => $now,
            'metadata' => $this->clearRuntimeMetadata($state),
        ])->save();
        $summary['conversation_states']++;

        $summary['sohbets'] += Sohbet::query()
            ->whereKey($task->sohbet_id)
            ->update([
                'ai_durumu' => AiConversationState::DURUM_IDLE,
                'ai_durum_metni' => null,
                'ai_planlanan_cevap_at' => null,
                'ai_durum_guncellendi_at' => $now,
            ]);

        return $summary;
    }

    private function clearRuntimeState(AiTurnLog $turnLog, CarbonInterface $now): array
    {
        $summary = ['conversation_states' => 0, 'sohbets' => 0];

        $state = AiConversationState::query()
            ->forCounterpart(
                (int) $turnLog->ai_user_id,
                (string) $turnLog->kanal,
                (string) $turnLog->hedef_tipi,
                (int) $turnLog->hedef_id,
            )
            ->first();

        if (!$state || !$this->stateBelongsToTurn($state, $turnLog)) {
            return $summary;
        }

        $state->forceFill([
            'ai_durumu' => AiConversationState::DURUM_IDLE,
            'planlanan_cevap_at' => null,
            'durum_guncellendi_at' => $now,
            'metadata' => $this->clearRuntimeMetadata($state),
        ])->save();
        $summary['conversation_states']++;

        if ($turnLog->sohbet_id) {
            $summary['sohbets'] += Sohbet::query()
                ->whereKey($turnLog->sohbet_id)
                ->update([
                    'ai_durumu' => AiConversationState::DURUM_IDLE,
                    'ai_durum_metni' => null,
                    'ai_planlanan_cevap_at' => null,
                    'ai_durum_guncellendi_at' => $now,
                ]);
        }

        return $summary;
    }

    private function stateBelongsToReference(AiConversationState $state, ?int $referenceMessageId): bool
    {
        $runtime = data_get($state->metadata, 'runtime', []);
        if (!is_array($runtime)) {
            return true;
        }

        $runtimeReference = $runtime['reference_message_id'] ?? null;
        if ($referenceMessageId !== null && $runtimeReference !== null && (int) $runtimeReference !== $referenceMessageId) {
            return false;
        }

        return in_array($state->ai_durumu, [
            AiConversationState::DURUM_QUEUED,
            AiConversationState::DURUM_TYPING,
        ], true);
    }

    private function stateBelongsToTurn(AiConversationState $state, AiTurnLog $turnLog): bool
    {
        $runtime = data_get($state->metadata, 'runtime', []);
        if (!is_array($runtime)) {
            return true;
        }

        $runtimeReference = $runtime['reference_message_id'] ?? null;
        $turnReference = $turnLog->gelen_mesaj_id ?? $turnLog->instagram_mesaj_id;

        if ($turnReference !== null && $runtimeReference !== null && (int) $runtimeReference !== (int) $turnReference) {
            return false;
        }

        $pendingTurnLogId = $runtime['pending_turn_log_id'] ?? null;
        if ($pendingTurnLogId !== null && (int) $pendingTurnLogId !== (int) $turnLog->id) {
            return false;
        }

        return in_array($state->ai_durumu, [
            AiConversationState::DURUM_QUEUED,
            AiConversationState::DURUM_TYPING,
        ], true);
    }

    private function clearRuntimeMetadata(AiConversationState $state): array
    {
        $metadata = is_array($state->metadata) ? $state->metadata : [];
        $metadata['runtime'] = array_replace(
            is_array($metadata['runtime'] ?? null) ? $metadata['runtime'] : [],
            [
                'reference_message_id' => null,
                'pending_turn_log_id' => null,
            ],
        );

        return $metadata;
    }

    private function typingDueAt(AiTurnLog $turnLog): ?Carbon
    {
        $value = data_get($turnLog->metadata, 'typing_due_at');
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function mergedMetadata(AiTurnLog $turnLog, array $metadata): array
    {
        return array_replace_recursive(
            is_array($turnLog->metadata) ? $turnLog->metadata : [],
            $metadata,
        );
    }
}
