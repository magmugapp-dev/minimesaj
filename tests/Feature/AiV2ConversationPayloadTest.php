<?php

use App\Models\AiPersonaProfile;
use App\Models\Eslesme;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\MesajServisi;
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
        'ai_durum_metni' => null,
        'ai_planlanan_cevap_at' => $plannedAt,
        'ai_durum_guncellendi_at' => now(),
    ]);

    Sanctum::actingAs($kullanici);

    $this->getJson("/api/dating/sohbetler/{$sohbet->id}/mesajlar")
        ->assertOk()
        ->assertJsonPath('ai.status', 'queued')
        ->assertJsonPath('ai.status_text', null)
        ->assertJsonPath('ai.planned_at', $plannedAt->toISOString());
});

it('uses persona language for ai messages and conversation peer payload', function () {
    $viewer = User::factory()->create([
        'hesap_durumu' => 'aktif',
        'dil' => 'tr',
    ]);
    $aiUser = User::factory()->aiKullanici()->create([
        'hesap_durumu' => 'aktif',
        'dil' => 'tr',
    ]);

    AiPersonaProfile::query()->create([
        'ai_user_id' => $aiUser->id,
        'ana_dil_kodu' => 'en',
        'ana_dil_adi' => 'Ingilizce',
        'konusma_tonu' => 'dogal',
        'konusma_stili' => 'akici',
        'metadata' => ['model_adi' => 'gemini-3.1-auto-quality'],
    ]);

    $eslesme = Eslesme::query()->create([
        'user_id' => $viewer->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'baslatan_user_id' => $viewer->id,
    ]);

    $sohbet = Sohbet::query()->create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
    ]);

    $mesaj = app(MesajServisi::class)->gonderAiMesaji($sohbet, $aiUser, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Hello from the AI side.',
    ]);

    expect($mesaj->dil_kodu)->toBe('en')
        ->and($mesaj->dil_adi)->toBe('Ingilizce');

    Sanctum::actingAs($viewer);

    $this->getJson('/api/dating/sohbetler')
        ->assertOk()
        ->assertJsonPath('data.0.peer_language_code', 'en')
        ->assertJsonPath('data.0.peer_language_name', 'Ingilizce');

    $this->getJson("/api/dating/sohbetler/{$sohbet->id}/mesajlar")
        ->assertOk()
        ->assertJsonPath('data.0.dil_kodu', 'en')
        ->assertJsonPath('data.0.dil_adi', 'Ingilizce');
});
