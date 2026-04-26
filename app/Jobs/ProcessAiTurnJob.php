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
use Illuminate\Support\Facades\Log;

class ProcessAiTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ResolvesAiTurnContext;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 300;
    public bool $failOnTimeout = true;

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
        Log::channel('ai')->info('AI turn job basladi.', $this->logContext());

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

            Log::channel('ai')->info('AI turn queued fazinda planlandi.', array_merge($this->logContext($context), [
                'planned_at' => $schedule['planned_at']->toISOString(),
            ]));

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
                Log::channel('ai')->info('AI turn generation fazi basladi.', $this->logContext($context));

                $processed = $turnOrchestrator->process($context, $state, $schedule['planned_at'], false);
                $finishedAt = now();
                $latencyMs = $startedAt->diffInMilliseconds($finishedAt);
                $turnLog = $processed['turn_log'] ?? null;
                if (!$turnLog) {
                    throw new \RuntimeException('Deferred AI reply icin turn log bulunamadi.');
                }

                $turnLog->forceFill([
                    'yanit_suresi_ms' => $latencyMs,
                    'metadata' => $this->mergedTurnLogMetadata(
                        $turnLog,
                        [
                            'generation_started_at' => $startedAt->toISOString(),
                            'generation_finished_at' => $finishedAt->toISOString(),
                        ],
                    ),
                ])->save();

                if ($context->turnType === 'reply' && $adapter->hasNewerIncoming($context)) {
                    $turnLog->forceFill([
                        'durum' => 'skipped',
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
                if ($replyText === '') {
                    $turnLog->forceFill([
                        'durum' => 'skipped',
                        'tamamlandi_at' => now(),
                        'metadata' => $this->mergedTurnLogMetadata(
                            $turnLog,
                            [
                                'delivery_skipped_at' => now()->toISOString(),
                                'delivery_skip_reason' => 'AI yaniti temiz metne parse edilemedigi icin teslim edilmedi.',
                            ],
                        ),
                    ])->save();

                    $this->clearRuntimeStatusIfCurrentTurn($context, $stateEngine, $state);
                    $legacySyncService->syncSkipped($context, 'AI yaniti temiz metne parse edilemedigi icin teslim edilmedi.');

                    return;
                }

                $typingSeconds = $turnScheduler->typingDelaySeconds($replyText);
                $typingDueAt = now()->addSeconds($typingSeconds);
                $typingStartedAt = now();
                $deliveryEnqueuedAt = now();

                Log::channel('ai')->info('AI turn generation tamamlandi, typing teslimi planlaniyor.', array_merge($this->logContext($context), [
                    'turn_log_id' => $turnLog->id,
                    'model' => $turnLog->model_adi,
                    'reply_length' => mb_strlen($replyText),
                    'latency_ms' => $latencyMs,
                    'typing_seconds' => $typingSeconds,
                    'typing_due_at' => $typingDueAt->toISOString(),
                ]));

                $turnLog->forceFill([
                    'durum' => 'typing',
                    'metadata' => $this->mergedTurnLogMetadata(
                        $turnLog,
                        [
                            'typing_started_at' => $typingStartedAt->toISOString(),
                            'typing_due_at' => $typingDueAt->toISOString(),
                            'simulated_typing_seconds' => $typingSeconds,
                            'delivery_enqueued_at' => $deliveryEnqueuedAt->toISOString(),
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
            $finishedAt = now();
            $latencyMs = $startedAt->diffInMilliseconds($finishedAt);

            if (isset($processed['turn_log'])) {
                $processed['turn_log']->forceFill([
                    'yanit_suresi_ms' => $latencyMs,
                    'metadata' => $this->mergedTurnLogMetadata(
                        $processed['turn_log'],
                        [
                            'generation_started_at' => $startedAt->toISOString(),
                            'generation_finished_at' => $finishedAt->toISOString(),
                        ],
                    ),
                ])->save();
            }

            $legacySyncService->syncCompleted($context, $processed['result'], $latencyMs);
            $this->broadcastStatus($context, AiConversationState::DURUM_IDLE, null, null);
        } catch (\Throwable $exception) {
            Log::channel('ai')->error('AI turn job hata ile durdu.', array_merge($this->logContext($context), [
                'attempt' => $this->attempts(),
                'hata' => mb_substr($exception->getMessage(), 0, 1000),
            ]));

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

    public function failed(\Throwable $exception): void
    {
        $context = $this->resolveAiTurnContext(
            $this->kanal,
            $this->turnType,
            $this->aiUserId,
            $this->sohbetId,
            $this->gelenMesajId,
            $this->instagramHesapId,
            $this->instagramMesajId,
        );

        Log::channel('ai')->error('AI turn job kalici olarak basarisiz oldu.', array_merge($this->logContext($context), [
            'hata' => mb_substr($exception->getMessage(), 0, 1000),
        ]));

        if (!$context) {
            return;
        }

        $stateEngine = app(AiStateEngine::class);
        $legacySyncService = app(AiLegacySyncService::class);
        $state = $stateEngine->stateForContext($context);

        if ($stateEngine->isRuntimeTurnCurrent($state, $this->referenceMessageId($context), null)) {
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

        $turnLog = \App\Models\AiTurnLog::query()
            ->where('kanal', $context->kanal)
            ->when($context->sohbet, fn ($query) => $query->where('sohbet_id', $context->sohbet->id))
            ->when($context->gelenMesaj, fn ($query) => $query->where('gelen_mesaj_id', $context->gelenMesaj->id))
            ->when($context->instagramMesaj, fn ($query) => $query->where('instagram_mesaj_id', $context->instagramMesaj->id))
            ->whereIn('durum', ['processing', 'generated', 'typing'])
            ->latest('id')
            ->first();

        $turnLog?->forceFill([
                'durum' => 'failed',
                'degerlendirme' => [
                    'accepted' => false,
                    'reasons' => ['queue_failed'],
                    'error' => mb_substr($exception->getMessage(), 0, 1000),
                ],
                'tamamlandi_at' => now(),
        ])->save();

        $legacySyncService->syncFailed($context, $exception, $this->attempts());
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

    private function logContext(?AiTurnContext $context = null): array
    {
        return [
            'kanal' => $this->kanal,
            'turn_type' => $this->turnType,
            'ai_user_id' => $this->aiUserId,
            'sohbet_id' => $this->sohbetId,
            'gelen_mesaj_id' => $this->gelenMesajId,
            'force_run' => $this->forceRun,
            'resolved_hedef_tipi' => $context?->hedefTipi(),
            'resolved_hedef_id' => $context?->hedefId(),
        ];
    }
}
