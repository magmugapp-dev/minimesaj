<?php

use App\Models\Eslesme;
use App\Models\Sohbet;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns ai runtime payload with conversation messages', function () {
    $kullanici = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);
    $aiUser = User::factory()->aiKullanici()->create([
        'hesap_durumu' => 'aktif',
    ]);

    $eslesme = Eslesme::query()->create([
        'user_id' => $kullanici->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'baslatan_user_id' => $kullanici->id,
    ]);

    $plannedAt = now()->copy()->setMicrosecond(0)->addSeconds(20);

    $sohbet = Sohbet::query()->create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
        'ai_durumu' => 'queued',
        'ai_durum_metni' => 'Dusunuyor...',
        'ai_planlanan_cevap_at' => $plannedAt,
        'ai_durum_guncellendi_at' => now(),
    ]);

    Sanctum::actingAs($kullanici);

    $this->getJson("/api/dating/sohbetler/{$sohbet->id}/mesajlar")
        ->assertOk()
        ->assertJsonPath('ai.status', 'queued')
        ->assertJsonPath('ai.status_text', 'Dusunuyor...')
        ->assertJsonPath('ai.planned_at', $plannedAt->toISOString());
});
