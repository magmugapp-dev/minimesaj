<?php

use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\MesajServisi;
use App\Support\AiMessageTextSanitizer;
use Laravel\Sanctum\Sanctum;

it('sanitizes ai json envelopes before persisting', function () {
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

    $message = app(MesajServisi::class)->gonderAiMesaji($conversation, $aiUser, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => '{"reply":"Selam, nasilsin?","memory":[]}',
        'dil_kodu' => 'tr',
        'dil_adi' => 'Turkish',
    ]);

    expect($message->mesaj_metni)->toBe('Selam, nasilsin?');
});

it('sanitizes legacy ai json envelopes while reading conversation messages', function () {
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

    Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $aiUser->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => "```json\n{\"reply\":\"Eski kayit cevabi\"}\n```",
        'ai_tarafindan_uretildi_mi' => true,
        'dil_kodu' => 'tr',
        'dil_adi' => 'Turkish',
    ]);

    Sanctum::actingAs($viewer);

    $this->getJson("/api/dating/sohbetler/{$conversation->id}/mesajlar")
        ->assertOk()
        ->assertJsonPath('data.0.mesaj_metni', 'Eski kayit cevabi');
});

it('drops malformed ai json envelopes instead of leaking raw payloads', function () {
    expect(AiMessageTextSanitizer::sanitize('{"reply":"S der'))->toBeNull()
        ->and(AiMessageTextSanitizer::sanitize("```json\n{\"reply\":\"S der\n```"))->toBeNull();
});
