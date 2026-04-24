<?php

use App\Events\AiTurnStatusUpdated;
use App\Jobs\DeliverAiReplyJob;
use App\Jobs\ProcessAiTurnJob;
use App\Models\AiConversationState;
use App\Models\AiPersonaProfile;
use App\Models\AiTurnLog;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\YapayZeka\V2\AiMessageInterpreter;
use App\Services\YapayZeka\V2\AiPersonaService;
use App\Services\YapayZeka\V2\AiStateEngine;
use App\Services\YapayZeka\V2\AiTurnOrchestrator;
use App\Services\YapayZeka\V2\AiTurnScheduler;
use App\Services\YapayZeka\V2\Data\AiGenerationResult;
use App\Services\YapayZeka\V2\Data\AiInterpretation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

it('queues generated dating replies into simulated typing before delivery', function () {
    Queue::fake();
    Event::fake([AiTurnStatusUpdated::class]);

    [$viewer, $aiUser, $conversation, $incomingMessage] = createDeferredAiChatFixture();
    $persona = AiPersonaProfile::query()->create([
        'ai_user_id' => $aiUser->id,
        'aktif_mi' => true,
        'dating_aktif_mi' => true,
        'instagram_aktif_mi' => true,
        'minimum_cevap_suresi_saniye' => 0,
        'maksimum_cevap_suresi_saniye' => 0,
    ]);

    $turnLog = AiTurnLog::query()->create([
        'ai_user_id' => $aiUser->id,
        'kanal' => 'dating',
        'turn_type' => 'reply',
        'hedef_tipi' => 'user',
        'hedef_id' => $viewer->id,
        'sohbet_id' => $conversation->id,
        'gelen_mesaj_id' => $incomingMessage->id,
        'durum' => 'processing',
        'baslatildi_at' => now(),
    ]);

    $this->mock(AiPersonaService::class, function (MockInterface $mock) use ($persona) {
        $mock->shouldReceive('ensureForUser')->andReturn($persona);
        $mock->shouldReceive('isChannelActive')->andReturnTrue();
    });

    $this->mock(AiMessageInterpreter::class, function (MockInterface $mock) {
        $mock->shouldReceive('interpret')->andReturn(
            new AiInterpretation(
                intent: 'question',
                emotion: 'neutral',
                energy: 'medium',
                riskLevel: 'low',
                expectation: 'keep_flow',
                topics: ['genel'],
                summary: 'Kullanici sohbeti surdurmek istiyor.',
            ),
        );
    });

    $this->mock(AiTurnOrchestrator::class, function (MockInterface $mock) use ($turnLog) {
        $mock->shouldReceive('process')
            ->once()
            ->withArgs(function ($context, $state, $plannedAt, $persistReply) {
                return $context->kanal === 'dating'
                    && $context->turnType === 'reply'
                    && $persistReply === false
                    && $plannedAt !== null;
            })
            ->andReturn([
                'result' => new AiGenerationResult(
                    'Selam, ben de iyiyim. Senin gunun nasil geciyor?',
                ),
                'turn_log' => $turnLog,
            ]);
    });

    $job = new ProcessAiTurnJob(
        'dating',
        'reply',
        $aiUser->id,
        $conversation->id,
        $incomingMessage->id,
    );

    $job->handle(
        app(AiPersonaService::class),
        app(AiMessageInterpreter::class),
        app(AiTurnScheduler::class),
        app(AiStateEngine::class),
        app(AiTurnOrchestrator::class),
        app(\App\Services\YapayZeka\V2\AiLegacySyncService::class),
        app(\App\Services\YapayZeka\V2\Channels\DatingChannelAdapter::class),
        app(\App\Services\YapayZeka\V2\Channels\InstagramChannelAdapter::class),
    );

    $conversation->refresh();
    $state = AiConversationState::query()->firstOrFail();
    $turnLog->refresh();

    expect($conversation->ai_durumu)->toBe('typing')
        ->and($conversation->ai_durum_metni)->toBe('Yaziyor...')
        ->and($conversation->ai_planlanan_cevap_at)->not->toBeNull()
        ->and($state->ai_durumu)->toBe('typing')
        ->and(data_get($state->metadata, 'runtime.reference_message_id'))->toBe($incomingMessage->id)
        ->and(data_get($state->metadata, 'runtime.pending_turn_log_id'))->toBe($turnLog->id)
        ->and($turnLog->durum)->toBe('typing')
        ->and(data_get($turnLog->metadata, 'simulated_typing_seconds'))->toBeInt()
        ->and(data_get($turnLog->metadata, 'generation_started_at'))->not->toBeNull()
        ->and(data_get($turnLog->metadata, 'generation_finished_at'))->not->toBeNull()
        ->and(data_get($turnLog->metadata, 'delivery_enqueued_at'))->not->toBeNull();

    Queue::assertPushed(DeliverAiReplyJob::class, function (DeliverAiReplyJob $job) use ($turnLog) {
        return $job->turnLogId === $turnLog->id;
    });

    Event::assertDispatched(AiTurnStatusUpdated::class, function (AiTurnStatusUpdated $event) use ($conversation) {
        return $event->sohbetId === $conversation->id
            && $event->status === 'typing'
            && $event->statusText === 'Yaziyor...'
            && $event->plannedAt !== null;
    });
});

it('skips deferred replies when generation cannot produce plain text', function () {
    Queue::fake();
    Event::fake([AiTurnStatusUpdated::class]);

    [$viewer, $aiUser, $conversation, $incomingMessage] = createDeferredAiChatFixture();
    $persona = AiPersonaProfile::query()->create([
        'ai_user_id' => $aiUser->id,
        'aktif_mi' => true,
        'dating_aktif_mi' => true,
        'instagram_aktif_mi' => true,
        'minimum_cevap_suresi_saniye' => 0,
        'maksimum_cevap_suresi_saniye' => 0,
    ]);

    $turnLog = AiTurnLog::query()->create([
        'ai_user_id' => $aiUser->id,
        'kanal' => 'dating',
        'turn_type' => 'reply',
        'hedef_tipi' => 'user',
        'hedef_id' => $viewer->id,
        'sohbet_id' => $conversation->id,
        'gelen_mesaj_id' => $incomingMessage->id,
        'durum' => 'processing',
        'baslatildi_at' => now(),
    ]);

    $this->mock(AiPersonaService::class, function (MockInterface $mock) use ($persona) {
        $mock->shouldReceive('ensureForUser')->andReturn($persona);
        $mock->shouldReceive('isChannelActive')->andReturnTrue();
    });

    $this->mock(AiMessageInterpreter::class, function (MockInterface $mock) {
        $mock->shouldReceive('interpret')->andReturn(
            new AiInterpretation(
                intent: 'question',
                emotion: 'neutral',
                energy: 'medium',
                riskLevel: 'low',
                expectation: 'keep_flow',
                topics: ['genel'],
                summary: 'Kullanici sohbeti surdurmek istiyor.',
            ),
        );
    });

    $this->mock(AiTurnOrchestrator::class, function (MockInterface $mock) use ($turnLog) {
        $mock->shouldReceive('process')
            ->once()
            ->andReturn([
                'result' => new AiGenerationResult(
                    '',
                    [],
                    '{"reply":"S der',
                ),
                'turn_log' => $turnLog,
            ]);
    });

    $job = new ProcessAiTurnJob(
        'dating',
        'reply',
        $aiUser->id,
        $conversation->id,
        $incomingMessage->id,
    );

    $job->handle(
        app(AiPersonaService::class),
        app(AiMessageInterpreter::class),
        app(AiTurnScheduler::class),
        app(AiStateEngine::class),
        app(AiTurnOrchestrator::class),
        app(\App\Services\YapayZeka\V2\AiLegacySyncService::class),
        app(\App\Services\YapayZeka\V2\Channels\DatingChannelAdapter::class),
        app(\App\Services\YapayZeka\V2\Channels\InstagramChannelAdapter::class),
    );

    $conversation->refresh();
    $turnLog->refresh();

    expect(
        Mesaj::query()
            ->where('sohbet_id', $conversation->id)
            ->where('gonderen_user_id', $aiUser->id)
            ->exists()
    )->toBeFalse()
        ->and($turnLog->durum)->toBe('skipped')
        ->and(data_get($turnLog->metadata, 'generation_started_at'))->not->toBeNull()
        ->and(data_get($turnLog->metadata, 'generation_finished_at'))->not->toBeNull()
        ->and(data_get($turnLog->metadata, 'delivery_skip_reason'))->toBe('AI yaniti temiz metne parse edilemedigi icin teslim edilmedi.')
        ->and(in_array($conversation->ai_durumu, [null, 'idle'], true))->toBeTrue()
        ->and($conversation->ai_durum_metni)->toBeNull();

    Queue::assertNotPushed(DeliverAiReplyJob::class);
});

it('delivers deferred ai replies and clears typing state', function () {
    Event::fake([AiTurnStatusUpdated::class]);

    [$viewer, $aiUser, $conversation, $incomingMessage] = createDeferredAiChatFixture();
    $turnLog = createDeferredTurnLog($viewer, $aiUser, $conversation, $incomingMessage);
    createDeferredConversationState(
        $viewer,
        $aiUser,
        $conversation,
        $turnLog,
        $incomingMessage,
    );

    $job = new DeliverAiReplyJob($turnLog->id);
    $job->handle(
        app(AiStateEngine::class),
        app(\App\Services\YapayZeka\V2\AiLegacySyncService::class),
        app(\App\Services\YapayZeka\V2\Channels\DatingChannelAdapter::class),
    );

    $conversation->refresh();
    $turnLog->refresh();
    $state = AiConversationState::query()->firstOrFail();
    $aiReply = Mesaj::query()
        ->where('sohbet_id', $conversation->id)
        ->where('gonderen_user_id', $aiUser->id)
        ->latest('id')
        ->first();

    expect($aiReply)->not->toBeNull()
        ->and($aiReply?->mesaj_metni)->toBe('Aksam biraz yorucuydu ama simdi iyiyim.')
        ->and($conversation->ai_durumu)->toBe('idle')
        ->and($conversation->ai_durum_metni)->toBeNull()
        ->and($conversation->ai_planlanan_cevap_at)->toBeNull()
        ->and($state->ai_durumu)->toBe('idle')
        ->and(data_get($state->metadata, 'runtime.pending_turn_log_id'))->toBeNull()
        ->and($turnLog->durum)->toBe('completed');

    Event::assertDispatched(AiTurnStatusUpdated::class, function (AiTurnStatusUpdated $event) use ($conversation) {
        return $event->sohbetId === $conversation->id
            && $event->status === 'idle'
            && $event->statusText === null;
    });
});

it('skips deferred delivery when a newer incoming message exists', function () {
    Event::fake([AiTurnStatusUpdated::class]);

    [$viewer, $aiUser, $conversation, $incomingMessage] = createDeferredAiChatFixture();
    $turnLog = createDeferredTurnLog($viewer, $aiUser, $conversation, $incomingMessage);
    createDeferredConversationState(
        $viewer,
        $aiUser,
        $conversation,
        $turnLog,
        $incomingMessage,
    );

    Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $viewer->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Daha yeni bir mesaj daha geldi.',
        'dil_kodu' => 'tr',
        'dil_adi' => 'Turkce',
    ]);

    $job = new DeliverAiReplyJob($turnLog->id);
    $job->handle(
        app(AiStateEngine::class),
        app(\App\Services\YapayZeka\V2\AiLegacySyncService::class),
        app(\App\Services\YapayZeka\V2\Channels\DatingChannelAdapter::class),
    );

    $conversation->refresh();
    $turnLog->refresh();

    expect(
        Mesaj::query()
            ->where('sohbet_id', $conversation->id)
            ->where('gonderen_user_id', $aiUser->id)
            ->exists()
    )->toBeFalse()
        ->and($turnLog->durum)->toBe('skipped')
        ->and($conversation->ai_durumu)->toBe('idle')
        ->and($conversation->ai_durum_metni)->toBeNull();
});

function createDeferredAiChatFixture(): array
{
    $viewer = User::factory()->create(['hesap_durumu' => 'aktif']);
    $aiUser = User::factory()->aiKullanici()->create(['hesap_durumu' => 'aktif']);

    $match = Eslesme::query()->create([
        'user_id' => $viewer->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'baslatan_user_id' => $viewer->id,
    ]);

    $conversation = Sohbet::query()->create([
        'eslesme_id' => $match->id,
        'durum' => 'aktif',
    ]);

    $incomingMessage = Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $viewer->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Selam, nasilsin?',
        'dil_kodu' => 'tr',
        'dil_adi' => 'Turkce',
    ]);

    $conversation->update([
        'son_mesaj_id' => $incomingMessage->id,
        'son_mesaj_tarihi' => $incomingMessage->created_at,
        'toplam_mesaj_sayisi' => 1,
    ]);

    return [$viewer, $aiUser, $conversation, $incomingMessage];
}

function createDeferredTurnLog(
    User $viewer,
    User $aiUser,
    Sohbet $conversation,
    Mesaj $incomingMessage,
): AiTurnLog {
    return AiTurnLog::query()->create([
        'ai_user_id' => $aiUser->id,
        'kanal' => 'dating',
        'turn_type' => 'reply',
        'hedef_tipi' => 'user',
        'hedef_id' => $viewer->id,
        'sohbet_id' => $conversation->id,
        'gelen_mesaj_id' => $incomingMessage->id,
        'durum' => 'typing',
        'cevap_plani' => ['aim' => 'keep_flow'],
        'cevap_metni' => 'Aksam biraz yorucuydu ama simdi iyiyim.',
        'model_adi' => 'gemini-2.5-flash',
        'prompt_ozeti' => 'Kisa ve samimi cevap',
        'baslatildi_at' => now()->subSeconds(5),
        'metadata' => [
            'typing_started_at' => now()->subSeconds(4)->toISOString(),
            'typing_due_at' => now()->addSeconds(4)->toISOString(),
            'simulated_typing_seconds' => 8,
        ],
    ]);
}

function createDeferredConversationState(
    User $viewer,
    User $aiUser,
    Sohbet $conversation,
    AiTurnLog $turnLog,
    Mesaj $incomingMessage,
): void {
    $typingDueAt = now()->addSeconds(4);

    AiConversationState::query()->create([
        'ai_user_id' => $aiUser->id,
        'kanal' => 'dating',
        'hedef_tipi' => 'user',
        'hedef_id' => $viewer->id,
        'ai_durumu' => 'typing',
        'planlanan_cevap_at' => $typingDueAt,
        'durum_guncellendi_at' => now(),
        'metadata' => [
            'runtime' => [
                'reference_message_id' => $incomingMessage->id,
                'pending_turn_log_id' => $turnLog->id,
            ],
        ],
    ]);

    $conversation->update([
        'ai_durumu' => 'typing',
        'ai_durum_metni' => 'Yaziyor...',
        'ai_planlanan_cevap_at' => $typingDueAt,
        'ai_durum_guncellendi_at' => now(),
    ]);
}
