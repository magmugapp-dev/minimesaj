<?php

use App\Models\AiConversationState;
use App\Models\AiTurnLog;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Models\YapayZekaGorevi;
use App\Services\YapayZeka\V2\AiTurnRecoveryService;
use Carbon\Carbon;

it('recovers stale processing ai turns and clears conversation runtime state', function () {
    $now = now()->startOfSecond();

    try {
        Carbon::setTestNow($now->copy()->subMinutes(20));

        [$viewer, $aiUser, $conversation, $incomingMessage] = createRecoveryAiChatFixture();
        $turnLog = createRecoveryTurnLog($viewer, $aiUser, $conversation, $incomingMessage, [
            'durum' => 'processing',
        ]);
        createRecoveryLegacyTask($conversation, $incomingMessage, $aiUser);
        createRecoveryRuntimeState($viewer, $aiUser, $conversation, $incomingMessage, null, 'queued');

        Carbon::setTestNow($now);

        $summary = app(AiTurnRecoveryService::class)->recover(
            now: $now,
            processingStaleMinutes: 10,
            typingGraceSeconds: 90,
        );

        $turnLog->refresh();
        $conversation->refresh();
        $state = AiConversationState::query()->firstOrFail();
        $legacyTask = YapayZekaGorevi::query()->firstOrFail();

        expect($summary['turn_logs'])->toBe(1)
            ->and($summary['legacy_tasks'])->toBe(1)
            ->and($summary['conversation_states'])->toBe(1)
            ->and($summary['sohbets'])->toBe(1)
            ->and($turnLog->durum)->toBe('failed')
            ->and(data_get($turnLog->metadata, 'recovered_at'))->not->toBeNull()
            ->and($legacyTask->durum)->toBe('basarisiz')
            ->and($legacyTask->hata_mesaji)->toContain('processing')
            ->and($conversation->ai_durumu)->toBe('idle')
            ->and($conversation->ai_durum_metni)->toBeNull()
            ->and($conversation->ai_planlanan_cevap_at)->toBeNull()
            ->and($state->ai_durumu)->toBe('idle')
            ->and(data_get($state->metadata, 'runtime.reference_message_id'))->toBeNull()
            ->and(data_get($state->metadata, 'runtime.pending_turn_log_id'))->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

it('recovers stale typing turns when the delayed delivery job is lost', function () {
    $now = now()->startOfSecond();

    try {
        Carbon::setTestNow($now->copy()->subMinutes(5));

        [$viewer, $aiUser, $conversation, $incomingMessage] = createRecoveryAiChatFixture();
        $turnLog = createRecoveryTurnLog($viewer, $aiUser, $conversation, $incomingMessage, [
            'durum' => 'typing',
            'cevap_metni' => 'Selam, buradayim.',
            'metadata' => [
                'typing_due_at' => now()->addSeconds(5)->toISOString(),
            ],
        ]);
        createRecoveryLegacyTask($conversation, $incomingMessage, $aiUser);
        createRecoveryRuntimeState($viewer, $aiUser, $conversation, $incomingMessage, $turnLog->id, 'typing');

        Carbon::setTestNow($now);

        $summary = app(AiTurnRecoveryService::class)->recover(
            now: $now,
            processingStaleMinutes: 10,
            typingGraceSeconds: 90,
        );

        $turnLog->refresh();
        $conversation->refresh();
        $legacyTask = YapayZekaGorevi::query()->firstOrFail();

        expect($summary['turn_logs'])->toBe(1)
            ->and($turnLog->durum)->toBe('failed')
            ->and(data_get($turnLog->degerlendirme, 'reasons.0'))->toBe('stale_turn_recovered')
            ->and($legacyTask->durum)->toBe('basarisiz')
            ->and($legacyTask->hata_mesaji)->toContain('typing')
            ->and($conversation->ai_durumu)->toBe('idle')
            ->and($conversation->ai_durum_metni)->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

it('recovers stale legacy tasks even when no open turn log remains', function () {
    $now = now()->startOfSecond();

    try {
        Carbon::setTestNow($now->copy()->subMinutes(30));

        [$viewer, $aiUser, $conversation, $incomingMessage] = createRecoveryAiChatFixture();
        createRecoveryLegacyTask($conversation, $incomingMessage, $aiUser);
        createRecoveryRuntimeState($viewer, $aiUser, $conversation, $incomingMessage, null, 'queued');

        Carbon::setTestNow($now);

        $summary = app(AiTurnRecoveryService::class)->recover(
            now: $now,
            processingStaleMinutes: 10,
            typingGraceSeconds: 90,
        );

        $conversation->refresh();
        $state = AiConversationState::query()->firstOrFail();
        $legacyTask = YapayZekaGorevi::query()->firstOrFail();

        expect($summary['turn_logs'])->toBe(0)
            ->and($summary['legacy_tasks'])->toBe(1)
            ->and($summary['conversation_states'])->toBe(1)
            ->and($summary['sohbets'])->toBe(1)
            ->and($legacyTask->durum)->toBe('basarisiz')
            ->and($legacyTask->hata_mesaji)->toContain('aktif turn log olmadan')
            ->and($conversation->ai_durumu)->toBe('idle')
            ->and($state->ai_durumu)->toBe('idle');
    } finally {
        Carbon::setTestNow();
    }
});

function createRecoveryAiChatFixture(): array
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
        'mesaj_metni' => 'Merhaba',
        'dil_kodu' => 'tr',
        'dil_adi' => 'Turkce',
    ]);

    return [$viewer, $aiUser, $conversation, $incomingMessage];
}

function createRecoveryTurnLog(
    User $viewer,
    User $aiUser,
    Sohbet $conversation,
    Mesaj $incomingMessage,
    array $overrides = [],
): AiTurnLog {
    return AiTurnLog::query()->create(array_merge([
        'ai_user_id' => $aiUser->id,
        'kanal' => 'dating',
        'turn_type' => 'reply',
        'hedef_tipi' => 'user',
        'hedef_id' => $viewer->id,
        'sohbet_id' => $conversation->id,
        'gelen_mesaj_id' => $incomingMessage->id,
        'durum' => 'processing',
        'baslatildi_at' => now(),
    ], $overrides));
}

function createRecoveryLegacyTask(Sohbet $conversation, Mesaj $incomingMessage, User $aiUser): YapayZekaGorevi
{
    return YapayZekaGorevi::query()->create([
        'sohbet_id' => $conversation->id,
        'gelen_mesaj_id' => $incomingMessage->id,
        'ai_user_id' => $aiUser->id,
        'durum' => 'istek_gonderildi',
        'deneme_sayisi' => 1,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-3.1-auto-quality',
        'istek_baslatildi_at' => now(),
    ]);
}

function createRecoveryRuntimeState(
    User $viewer,
    User $aiUser,
    Sohbet $conversation,
    Mesaj $incomingMessage,
    ?int $pendingTurnLogId,
    string $status,
): void {
    $plannedAt = now()->addSeconds(30);

    AiConversationState::query()->create([
        'ai_user_id' => $aiUser->id,
        'kanal' => 'dating',
        'hedef_tipi' => 'user',
        'hedef_id' => $viewer->id,
        'ai_durumu' => $status,
        'planlanan_cevap_at' => $plannedAt,
        'durum_guncellendi_at' => now(),
        'metadata' => [
            'runtime' => [
                'reference_message_id' => $incomingMessage->id,
                'pending_turn_log_id' => $pendingTurnLogId,
            ],
        ],
    ]);

    $conversation->update([
        'ai_durumu' => $status,
        'ai_durum_metni' => $status === 'typing' ? 'Yaziyor...' : null,
        'ai_planlanan_cevap_at' => $plannedAt,
        'ai_durum_guncellendi_at' => now(),
    ]);
}
