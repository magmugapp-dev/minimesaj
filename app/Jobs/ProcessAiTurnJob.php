<?php

namespace App\Jobs;

use App\Events\AiTurnStatusUpdated;
use App\Models\AiConversationState;
use App\Models\InstagramHesap;
use App\Models\InstagramMesaj;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\YapayZeka\V2\AiLegacySyncService;
use App\Services\YapayZeka\V2\AiMessageInterpreter;
use App\Services\YapayZeka\V2\AiPersonaService;
use App\Services\YapayZeka\V2\AiStateEngine;
use App\Services\YapayZeka\V2\AiTurnOrchestrator;
use App\Services\YapayZeka\V2\AiTurnScheduler;
use App\Services\YapayZeka\V2\Channels\DatingChannelAdapter;
use App\Services\YapayZeka\V2\Channels\InstagramChannelAdapter;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAiTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $context = $this->resolveContext();
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
                $schedule['status_text'],
            );
            $legacySyncService->syncQueued($context, $schedule['planned_at']);
            $this->broadcastStatus($context, AiConversationState::DURUM_QUEUED, $schedule['status_text'], $schedule['planned_at']);

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
            $stateEngine->setRuntimeStatus($context, $state, AiConversationState::DURUM_IDLE, null, null);
            $legacySyncService->syncSkipped($context, 'Daha yeni bir kullanici mesaji bulundugu icin atlandi.');
            $this->broadcastStatus($context, AiConversationState::DURUM_IDLE, null, null);

            return;
        }

        $stateEngine->setRuntimeStatus(
            $context,
            $state,
            AiConversationState::DURUM_TYPING,
            $schedule['planned_at'],
            $schedule['status_text'],
        );
        $legacySyncService->syncStarted($context);
        $this->broadcastStatus($context, AiConversationState::DURUM_TYPING, $schedule['status_text'], $schedule['planned_at']);

        $startedAt = now();

        try {
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
            $stateEngine->setRuntimeStatus($context, $state, AiConversationState::DURUM_IDLE, null, null);
            $legacySyncService->syncFailed($context, $exception, $this->attempts());
            $this->broadcastStatus($context, AiConversationState::DURUM_IDLE, null, null);

            throw $exception;
        }
    }

    private function resolveContext(): ?AiTurnContext
    {
        $aiUser = User::query()->find($this->aiUserId);
        if (!$aiUser || $aiUser->hesap_tipi !== 'ai') {
            return null;
        }

        if ($this->kanal === 'instagram') {
            $hesap = InstagramHesap::query()->find($this->instagramHesapId);
            $mesaj = InstagramMesaj::query()->with('kisi')->find($this->instagramMesajId);

            if (!$hesap || !$mesaj || !$mesaj->kisi) {
                return null;
            }

            return new AiTurnContext(
                kanal: 'instagram',
                turnType: $this->turnType,
                aiUser: $aiUser,
                instagramHesap: $hesap,
                instagramKisi: $mesaj->kisi,
                instagramMesaj: $mesaj,
            );
        }

        $sohbet = Sohbet::query()->with('eslesme')->find($this->sohbetId);
        if (!$sohbet || !$sohbet->eslesme) {
            return null;
        }

        $gelenMesaj = $this->gelenMesajId ? Mesaj::query()->find($this->gelenMesajId) : null;
        $hedefUserId = (int) $sohbet->eslesme->user_id === (int) $aiUser->id
            ? (int) $sohbet->eslesme->eslesen_user_id
            : (int) $sohbet->eslesme->user_id;
        $hedefUser = User::query()->find($hedefUserId);

        if (!$hedefUser) {
            return null;
        }

        return new AiTurnContext(
            kanal: 'dating',
            turnType: $this->turnType,
            aiUser: $aiUser,
            sohbet: $sohbet,
            gelenMesaj: $gelenMesaj,
            hedefUser: $hedefUser,
        );
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
}
