<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiConversationState;
use App\Models\AiTurnLog;
use App\Services\YapayZeka\V2\Channels\AiChannelAdapterInterface;
use App\Services\YapayZeka\V2\Channels\DatingChannelAdapter;
use App\Services\YapayZeka\V2\Channels\InstagramChannelAdapter;
use App\Services\YapayZeka\V2\Data\AiGenerationResult;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Carbon\CarbonInterface;

class AiTurnOrchestrator
{
    public function __construct(
        private ?AiEngineConfigService $engineConfigService = null,
        private ?AiPersonaService $personaService = null,
        private ?AiGuardrailService $guardrailService = null,
        private ?AiMessageInterpreter $messageInterpreter = null,
        private ?AiStateEngine $stateEngine = null,
        private ?AiMemoryService $memoryService = null,
        private ?AiResponsePlanner $responsePlanner = null,
        private ?AiResponseGenerator $responseGenerator = null,
        private ?AiResponseEvaluator $responseEvaluator = null,
        private ?DatingChannelAdapter $datingAdapter = null,
        private ?InstagramChannelAdapter $instagramAdapter = null,
    ) {
        $this->engineConfigService ??= app(AiEngineConfigService::class);
        $this->personaService ??= app(AiPersonaService::class);
        $this->guardrailService ??= app(AiGuardrailService::class);
        $this->messageInterpreter ??= app(AiMessageInterpreter::class);
        $this->stateEngine ??= app(AiStateEngine::class);
        $this->memoryService ??= app(AiMemoryService::class);
        $this->responsePlanner ??= app(AiResponsePlanner::class);
        $this->responseGenerator ??= app(AiResponseGenerator::class);
        $this->responseEvaluator ??= app(AiResponseEvaluator::class);
        $this->datingAdapter ??= app(DatingChannelAdapter::class);
        $this->instagramAdapter ??= app(InstagramChannelAdapter::class);
    }

    public function process(
        AiTurnContext $context,
        ?AiConversationState $state = null,
        ?CarbonInterface $plannedAt = null,
        bool $persistReply = true,
    ): array {
        $config = $this->engineConfigService->activeConfig();
        $persona = $this->personaService->ensureForUser($context->aiUser);
        $adapter = $this->adapterFor($context->kanal);
        $state ??= $this->stateEngine->stateForContext($context);

        $interpretation = $this->messageInterpreter->interpret(
            $context->turnType === 'first_message'
                ? $context->hedefGorunenAdi()
                : $context->referansMetni(),
            $context,
        );

        $stateSnapshot = $this->stateEngine->applyIncoming($context, $state, $interpretation);
        $memoryAnalysis = $context->turnType === 'reply'
            ? $this->memoryService->analyzeIncoming(
                $context,
                $context->referansMetni(),
                $interpretation,
                $stateSnapshot,
            )
            : ['extraction' => ['candidates' => [], 'provider' => 'none', 'raw' => null], 'stored' => [], 'contradictions' => []];
        $contradictions = $memoryAnalysis['contradictions'] ?? [];
        $surfacedContradiction = $this->selectSurfacedContradiction($contradictions);
        $memories = $this->memoryService->recall($context);
        $this->memoryService->markUsed($memories);
        $plan = $this->responsePlanner->plan(
            $interpretation,
            $stateSnapshot,
            $persona,
            $context->turnType === 'first_message',
            $contradictions,
        );

        $turnLog = AiTurnLog::query()->create([
            'ai_user_id' => $context->aiUser->id,
            'kanal' => $context->kanal,
            'turn_type' => $context->turnType,
            'hedef_tipi' => $context->hedefTipi(),
            'hedef_id' => $context->hedefId(),
            'sohbet_id' => $context->sohbet?->id,
            'gelen_mesaj_id' => $context->gelenMesaj?->id,
            'instagram_hesap_id' => $context->instagramHesap?->id,
            'instagram_kisi_id' => $context->instagramKisi?->id,
            'instagram_mesaj_id' => $context->instagramMesaj?->id,
            'durum' => 'processing',
            'yorumlama' => $interpretation->toArray(),
            'cevap_plani' => $plan->toArray(),
            'planlanan_at' => $plannedAt,
            'baslatildi_at' => now(),
            'metadata' => [
                'memory_extraction' => $memoryAnalysis['extraction'] ?? null,
                'stored_memory_ids' => $memoryAnalysis['stored'] ?? [],
                'contradictions' => $contradictions,
                'surfaced_contradiction' => $surfacedContradiction,
            ],
        ]);

        try {
            $violations = $context->turnType === 'reply'
                ? $this->guardrailService->detectViolations($context->referansMetni(), $persona, $context->kanal)
                : ['blocked' => false, 'matches' => []];

            $generation = $violations['blocked']
                ? new AiGenerationResult(
                    $this->guardrailService->boundaryReply($violations['matches'], $context->kanal),
                    [],
                    null,
                    $config->model_adi,
                    0,
                    0,
                    'Guardrail blokaji ile lokal cevap uretildi.',
                )
                : $this->responseGenerator->generate(
                    $context,
                    $adapter,
                    $config,
                    $persona,
                $stateSnapshot,
                $plan,
                $memories,
                $contradictions,
                $surfacedContradiction,
            );

            $evaluation = $this->responseEvaluator->evaluate(
                $generation->replyText,
                $persona,
                $plan,
                $this->guardrailService->detectViolations($generation->replyText, $persona, $context->kanal),
            );

            if (!$evaluation['accepted']) {
                $generation = $this->responseGenerator->generate(
                    $context,
                    $adapter,
                    $config,
                    $persona,
                    $stateSnapshot,
                    $plan,
                    $memories,
                    $contradictions,
                    $surfacedContradiction,
                    $evaluation['reasons'],
                );

                $evaluation = $this->responseEvaluator->evaluate(
                    $generation->replyText,
                    $persona,
                    $plan,
                    $this->guardrailService->detectViolations($generation->replyText, $persona, $context->kanal),
                );
            }

            if (!$evaluation['accepted']) {
                $generation = $generation->withReply($this->responseEvaluator->fallbackReply($plan));
                $evaluation = ['accepted' => true, 'reasons' => ['fallback_reply']];
            }

            $turnLogPayload = [
                'kullanilan_hafiza_idleri' => $memories->pluck('id')->values()->all(),
                'degerlendirme' => $evaluation,
                'prompt_ozeti' => $generation->promptSummary,
                'cevap_metni' => $generation->replyText,
                'ham_cevap' => $generation->rawResponse,
                'saglayici_tipi' => $config->saglayici_tipi,
                'model_adi' => $generation->model,
                'giris_token_sayisi' => $generation->inputTokens,
                'cikis_token_sayisi' => $generation->outputTokens,
            ];

            if (!$persistReply) {
                $turnLog->update([
                    ...$turnLogPayload,
                    'durum' => 'generated',
                    'tamamlandi_at' => null,
                ]);

                return [
                    'result' => $generation,
                    'evaluation' => $evaluation,
                    'turn_log' => $turnLog,
                ];
            }

            $persisted = $adapter->persistReply($context, $context->aiUser, $generation->replyText);
            $adapter->markIncomingHandled($context);

            $this->stateEngine->markReplyPersisted(
                $context,
                $state,
                $plan->aim,
                $generation->replyText,
            );

            $turnLog->update([
                ...$turnLogPayload,
                'durum' => 'completed',
                'tamamlandi_at' => now(),
            ]);

            return [
                'result' => $generation,
                'evaluation' => $evaluation,
                'turn_log' => $turnLog,
                'persisted' => $persisted,
            ];
        } catch (\Throwable $exception) {
            $turnLog->update([
                'durum' => 'failed',
                'degerlendirme' => [
                    'accepted' => false,
                    'reasons' => ['exception'],
                    'error' => mb_substr($exception->getMessage(), 0, 1000),
                ],
                'tamamlandi_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function adapterFor(string $kanal): AiChannelAdapterInterface
    {
        return $kanal === 'instagram'
            ? $this->instagramAdapter
            : $this->datingAdapter;
    }

    private function selectSurfacedContradiction(array $contradictions): ?array
    {
        return collect($contradictions)
            ->filter(fn (array $signal) => (bool) ($signal['should_surface'] ?? false))
            ->sort(function (array $left, array $right): int {
                return [
                    (int) ($right['priority'] ?? 0),
                    (int) ($right['importance'] ?? 0),
                    (float) ($right['confidence'] ?? 0),
                ] <=> [
                    (int) ($left['priority'] ?? 0),
                    (int) ($left['importance'] ?? 0),
                    (float) ($left['confidence'] ?? 0),
                ];
            })
            ->values()
            ->first();
    }
}
