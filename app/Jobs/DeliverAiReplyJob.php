<?php

namespace App\Jobs;

use App\Events\AiTurnStatusUpdated;
use App\Jobs\Concerns\ResolvesAiTurnContext;
use App\Models\AiConversationState;
use App\Models\AiTurnLog;
use App\Services\YapayZeka\V2\AiLegacySyncService;
use App\Services\YapayZeka\V2\AiStateEngine;
use App\Services\YapayZeka\V2\Channels\DatingChannelAdapter;
use App\Services\YapayZeka\V2\Data\AiGenerationResult;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use App\Support\AiMessageTextSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverAiReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ResolvesAiTurnContext;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(public int $turnLogId) {}

    public function handle(
        AiStateEngine $stateEngine,
        AiLegacySyncService $legacySyncService,
        DatingChannelAdapter $datingChannelAdapter,
    ): void {
        $turnLog = AiTurnLog::query()->find($this->turnLogId);
        if (!$turnLog || $turnLog->kanal !== 'dating') {
            return;
        }

        $context = $this->resolveAiTurnContext(
            $turnLog->kanal,
            $turnLog->turn_type,
            (int) $turnLog->ai_user_id,
            $turnLog->sohbet_id,
            $turnLog->gelen_mesaj_id,
            $turnLog->instagram_hesap_id,
            $turnLog->instagram_mesaj_id,
        );

        if (!$context) {
            return;
        }

        $state = $stateEngine->stateForContext($context);
        $isCurrentRuntimeTurn = $stateEngine->isRuntimeTurnCurrent(
            $state,
            $this->referenceMessageId($context),
            $turnLog->id,
        );
        $hasNewerIncoming = $context->turnType === 'reply'
            && $datingChannelAdapter->hasNewerIncoming($context);

        if (!$isCurrentRuntimeTurn || $hasNewerIncoming) {
            $this->skipTurnLog(
                $turnLog,
                $context,
                $stateEngine,
                $state,
                $legacySyncService,
                $hasNewerIncoming
                    ? 'Daha yeni bir kullanici mesaji bulundugu icin teslim edilmedi.'
                    : 'Bekleyen typing oturumu guncel olmadigi icin teslim edilmedi.',
            );

            return;
        }

        $replyText = AiMessageTextSanitizer::sanitize($turnLog->cevap_metni);
        if ($replyText === null || trim($replyText) === '') {
            $this->skipTurnLog(
                $turnLog,
                $context,
                $stateEngine,
                $state,
                $legacySyncService,
                'Bekleyen AI cevabi bos oldugu icin teslim edilmedi.',
            );

            return;
        }

        try {
            $datingChannelAdapter->persistReply($context, $context->aiUser, $replyText);
            $datingChannelAdapter->markIncomingHandled($context);

            $stateEngine->markReplyPersisted(
                $context,
                $state,
                (string) data_get($turnLog->cevap_plani, 'aim', 'keep_flow'),
                $replyText,
            );

            $legacySyncService->syncCompleted(
                $context,
                new AiGenerationResult(
                    $replyText,
                    [],
                    $turnLog->ham_cevap,
                    $turnLog->model_adi,
                    (int) ($turnLog->giris_token_sayisi ?? 0),
                    (int) ($turnLog->cikis_token_sayisi ?? 0),
                    (string) ($turnLog->prompt_ozeti ?? ''),
                ),
                (int) ($turnLog->yanit_suresi_ms ?? 0),
            );

            $turnLog->forceFill([
                'durum' => 'completed',
                'tamamlandi_at' => now(),
                'metadata' => $this->mergedTurnLogMetadata(
                    $turnLog,
                    ['typing_delivered_at' => now()->toISOString()],
                ),
            ])->save();

            $this->broadcastStatus($context, AiConversationState::DURUM_IDLE, null, null);
        } catch (\Throwable $exception) {
            if (
                $stateEngine->isRuntimeTurnCurrent(
                    $state,
                    $this->referenceMessageId($context),
                    $turnLog->id,
                )
            ) {
                $stateEngine->setRuntimeStatus(
                    $context,
                    $state,
                    AiConversationState::DURUM_IDLE,
                    null,
                    null,
                    $this->runtimeMetadata($context, null),
                );
                $this->broadcastStatus($context, AiConversationState::DURUM_IDLE, null, null);
            }

            $turnLog->forceFill([
                'durum' => 'failed',
                'tamamlandi_at' => now(),
                'metadata' => $this->mergedTurnLogMetadata(
                    $turnLog,
                    ['delivery_error' => mb_substr($exception->getMessage(), 0, 500)],
                ),
            ])->save();

            $legacySyncService->syncFailed($context, $exception, $this->attempts());

            throw $exception;
        }
    }

    private function skipTurnLog(
        AiTurnLog $turnLog,
        AiTurnContext $context,
        AiStateEngine $stateEngine,
        \App\Models\AiConversationState $state,
        AiLegacySyncService $legacySyncService,
        string $reason,
    ): void {
        $turnLog->forceFill([
            'durum' => 'skipped',
            'tamamlandi_at' => now(),
            'metadata' => $this->mergedTurnLogMetadata(
                $turnLog,
                [
                    'delivery_skipped_at' => now()->toISOString(),
                    'delivery_skip_reason' => $reason,
                ],
            ),
        ])->save();

        $legacySyncService->syncSkipped($context, $reason);

        if (
            $stateEngine->isRuntimeTurnCurrent(
                $state,
                $this->referenceMessageId($context),
                $turnLog->id,
            )
        ) {
            $stateEngine->setRuntimeStatus(
                $context,
                $state,
                AiConversationState::DURUM_IDLE,
                null,
                null,
                $this->runtimeMetadata($context, null),
            );
            $this->broadcastStatus($context, AiConversationState::DURUM_IDLE, null, null);
        }
    }

    private function broadcastStatus(
        AiTurnContext $context,
        string $status,
        ?string $statusText,
        ?\Carbon\CarbonInterface $plannedAt,
    ): void {
        if (!$context->sohbet) {
            return;
        }

        AiTurnStatusUpdated::dispatch(
            $context->sohbet->id,
            $status,
            $statusText,
            $plannedAt?->toISOString(),
        );
    }

    private function referenceMessageId(AiTurnContext $context): ?int
    {
        return $context->gelenMesaj?->id ?? $context->instagramMesaj?->id;
    }

    private function runtimeMetadata(AiTurnContext $context, ?int $pendingTurnLogId): array
    {
        return [
            'reference_message_id' => $this->referenceMessageId($context),
            'pending_turn_log_id' => $pendingTurnLogId,
        ];
    }

    private function mergedTurnLogMetadata(AiTurnLog $turnLog, array $metadata): array
    {
        $current = is_array($turnLog->metadata) ? $turnLog->metadata : [];

        return array_replace_recursive($current, $metadata);
    }
}
