<?php

namespace App\Jobs;

use App\Events\AiTurnStatusUpdated;
use App\Jobs\Concerns\ResolvesAiTurnContext;
use App\Models\AiConversationState;
use App\Services\YapayZeka\V2\AiLegacySyncService;
use App\Services\YapayZeka\V2\AiMessageInterpreter;
use App\Services\YapayZeka\V2\AiPersonaService;
use App\Services\YapayZeka\V2\AiStateEngine;
use App\Services\YapayZeka\V2\AiTurnOrchestrator;
use App\Services\YapayZeka\V2\AiTurnScheduler;
use App\Services\YapayZeka\V2\Channels\DatingChannelAdapter;
use App\Services\YapayZeka\V2\Channels\InstagramChannelAdapter;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use App\Support\AiMessageTextSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAiTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ResolvesAiTurnContext;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public string $kanal,
        public string $turnType,
        public int $aiUserId,
        public ?int $sohbetId = null,
        public ?int $gelenMesajId = null,
        public ?int $instagramHesapId = null,
        public ?int $instagramMesajId = null,
        public bool $forceRun = false,
    ) {}

    public function handle(
        AiPersonaService $personaService,
        AiMessageInterpreter $messageInterpreter,
        AiTurnScheduler $turnScheduler,
        AiStateEngine $stateEngine,
        AiTurnOrchestrator $turnOrchestrator,
        AiLegacySyncService $legacySyncService,
        DatingChannelAdapter $datingChannelAdapter,
        InstagramChannelAdapter $instagramChannelAdapter,
    ): void {
        $context = $this->resolveAiTurnContext(
            $this->kanal,
            $this->turnType,
            $this->aiUserId,
            $this->sohbetId,
            $this->gelenMesajId,
            $this->instagramHesapId,
            $this->instagramMesajId,
        );
        if (!$context) {
            return;
        }

        $persona = $personaService->ensureForUser($context->aiUser);
        if (!$personaService->isChannelActive($persona, $context->kanal)) {
            return;
        }

        if ($context->turnType === 'first_message' && !$persona->ilk_mesaj_atar_mi) {
            return;
        }

        $state = $stateEngine->stateForContext($context);
        $interpretation = $messageInterpreter->interpret(
            $context->turnType === 'first_message' ? $context->hedefGorunenAdi() : $context->referansMetni(),
            $context,
        );
        $schedule = $turnScheduler->schedule($context, $persona, $interpretation);

        if (!$this->forceRun && now()->lt($schedule['planned_at'])) {
            $stateEngine->setRuntimeStatus(
                $context,
                $state,
                AiConversationState::DURUM_QUEUED,
                $schedule['planned_at'],
                null,
                $this->runtimeMetadata($context, null),
            );
            $legacySyncService->syncQueued($context, $schedule['planned_at']);
            $this->broadcastStatus($context, AiConversationState::DURUM_QUEUED, null, $schedule['planned_at']);

            self::dispatch(
                $this->kanal,
                $this->turnType,
                $this->aiUserId,
                $this->sohbetId,
                $this->gelenMesajId,
                $this->instagramHesapId,
                $this->instagramMesajId,
                true,
            )->delay($schedule['planned_at']);

            return;
        }

        $adapter = $context->kanal === 'instagram' ? $instagramChannelAdapter : $datingChannelAdapter;
        if ($context->turnType === 'reply' && $adapter->hasNewerIncoming($context)) {
            $this->clearRuntimeStatusIfCurrentTurn($context, $stateEngine, $state);
            $legacySyncService->syncSkipped($context, 'Daha yeni bir kullanici mesaji bulundugu icin atlandi.');

            return;
        }

        $legacySyncService->syncStarted($context);

        $startedAt = now();

        try {
            if ($context->kanal === 'dating') {
                $processed = $turnOrchestrator->process($context, $state, $schedule['planned_at'], false);
                $latencyMs = $startedAt->diffInMilliseconds(now());
                $turnLog = $processed['turn_log'] ?? null;
                if (!$turnLog) {
                    throw new \RuntimeException('Deferred AI reply icin turn log bulunamadi.');
                }

                if ($context->turnType === 'reply' && $adapter->hasNewerIncoming($context)) {
                    $turnLog->forceFill([
                        'durum' => 'skipped',
                        'yanit_suresi_ms' => $latencyMs,
                        'tamamlandi_at' => now(),
                        'metadata' => $this->mergedTurnLogMetadata(
                            $turnLog,
                            [
                                'delivery_skip_reason' => 'Daha yeni bir kullanici mesaji bulundugu icin typing baslatilmadi.',
                                'delivery_skipped_at' => now()->toISOString(),
                            ],
                        ),
                    ])->save();

                    $this->clearRuntimeStatusIfCurrentTurn($context, $stateEngine, $state);
                    $legacySyncService->syncSkipped($context, 'Daha yeni bir kullanici mesaji bulundugu icin typing baslatilmadi.');

                    return;
                }

                $replyText = AiMessageTextSanitizer::sanitize($processed['result']->replyText) ?? '';
                $typingSeconds = $turnScheduler->typingDelaySeconds($replyText);
                $typingDueAt = now()->addSeconds($typingSeconds);

                $turnLog->forceFill([
                    'durum' => 'typing',
                    'yanit_suresi_ms' => $latencyMs,
                    'metadata' => $this->mergedTurnLogMetadata(
                        $turnLog,
                        [
                            'typing_started_at' => now()->toISOString(),
                            'typing_due_at' => $typingDueAt->toISOString(),
                            'simulated_typing_seconds' => $typingSeconds,
                        ],
                    ),
                ])->save();

                $stateEngine->setRuntimeStatus(
                    $context,
                    $state,
                    AiConversationState::DURUM_TYPING,
                    $typingDueAt,
                    'Yaziyor...',
                    $this->runtimeMetadata($context, $turnLog->id),
                );
                $this->broadcastStatus($context, AiConversationState::DURUM_TYPING, 'Yaziyor...', $typingDueAt);

                DeliverAiReplyJob::dispatch($turnLog->id)->delay($typingDueAt);

                return;
            }

            $stateEngine->setRuntimeStatus(
                $context,
                $state,
                AiConversationState::DURUM_TYPING,
                $schedule['planned_at'],
                'Yaziyor...',
                $this->runtimeMetadata($context, null),
            );
            $this->broadcastStatus($context, AiConversationState::DURUM_TYPING, 'Yaziyor...', $schedule['planned_at']);

            $processed = $turnOrchestrator->process($context, $state, $schedule['planned_at']);
            $latencyMs = $startedAt->diffInMilliseconds(now());

            if (isset($processed['turn_log'])) {
                $processed['turn_log']->forceFill([
                    'yanit_suresi_ms' => $latencyMs,
                ])->save();
            }

            $legacySyncService->syncCompleted($context, $processed['result'], $latencyMs);
            $this->broadcastStatus($context, AiConversationState::DURUM_IDLE, null, null);
        } catch (\Throwable $exception) {
            $stateEngine->setRuntimeStatus(
                $context,
                $state,
                AiConversationState::DURUM_IDLE,
                null,
                null,
                $this->runtimeMetadata($context, null),
            );
            $legacySyncService->syncFailed($context, $exception, $this->attempts());
            $this->broadcastStatus($context, AiConversationState::DURUM_IDLE, null, null);

            throw $exception;
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

    private function clearRuntimeStatusIfCurrentTurn(
        AiTurnContext $context,
        AiStateEngine $stateEngine,
        \App\Models\AiConversationState $state,
        ?int $pendingTurnLogId = null,
    ): void {
        if (
            ! $stateEngine->isRuntimeTurnCurrent(
                $state,
                $this->referenceMessageId($context),
                $pendingTurnLogId,
            )
        ) {
            return;
        }

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

    private function mergedTurnLogMetadata(\App\Models\AiTurnLog $turnLog, array $metadata): array
    {
        $current = is_array($turnLog->metadata) ? $turnLog->metadata : [];

        return array_replace_recursive($current, $metadata);
    }
}
