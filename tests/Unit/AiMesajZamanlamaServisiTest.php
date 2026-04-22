<?php

use Tests\TestCase;
use App\Models\AiAyar;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\YapayZeka\AiMesajZamanlamaServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(TestCase::class, RefreshDatabase::class);

it('ignores minute based random delay for dating replies', function () {
    Carbon::setTestNow('2026-04-16 12:00:00');

    $kullanici = User::factory()->create();
    $aiUser = User::factory()->aiKullanici()->create([
        'cevrim_ici_mi' => true,
    ]);

    AiAyar::create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
        'minimum_cevap_suresi_saniye' => 5,
        'maksimum_cevap_suresi_saniye' => 40,
        'rastgele_gecikme_dakika' => 15,
        'uyku_baslangic' => '23:00',
        'uyku_bitis' => '07:30',
        'saat_dilimi' => 'Europe/Istanbul',
    ]);

    $eslesme = Eslesme::create([
        'user_id' => $kullanici->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'baslatan_user_id' => $kullanici->id,
    ]);

    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
        'toplam_mesaj_sayisi' => 0,
    ]);

    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $kullanici->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Selam',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $durum = app(AiMesajZamanlamaServisi::class)->sohbetCevabiDurumu(
        $mesaj,
        $aiUser->fresh('aiAyar'),
        now(),
    );

    $fark = $mesaj->created_at->diffInSeconds($durum['planlanan_at']);

    expect($fark)->toBeGreaterThanOrEqual(5);
    expect($fark)->toBeLessThanOrEqual(40);
    expect($durum['bekleme_nedeni'])->toBe('cevap_akisi');

    Carbon::setTestNow();
});
