<?php

use App\Models\Odeme;
use App\Models\PuanPaketi;
use App\Models\User;
use App\Services\FisDogrulamaServisi;
use Laravel\Sanctum\Sanctum;

it('lists only active point packages for the requested platform', function () {
    PuanPaketi::query()->create([
        'kod' => 'kredi_25',
        'android_urun_kodu' => 'magmug.kredi25',
        'ios_urun_kodu' => 'magmug.kredi25.ios',
        'puan' => 25,
        'fiyat' => 49.99,
        'para_birimi' => 'TRY',
        'aktif' => true,
        'sira' => 1,
    ]);

    PuanPaketi::query()->create([
        'kod' => 'kredi_60',
        'android_urun_kodu' => 'magmug.kredi60',
        'ios_urun_kodu' => 'magmug.kredi60.ios',
        'puan' => 60,
        'fiyat' => 99.99,
        'para_birimi' => 'TRY',
        'rozet' => 'EN POPULER',
        'onerilen_mi' => true,
        'aktif' => true,
        'sira' => 2,
    ]);

    PuanPaketi::query()->create([
        'kod' => 'kredi_pasif',
        'android_urun_kodu' => 'magmug.kredi_pasif',
        'ios_urun_kodu' => 'magmug.kredi_pasif.ios',
        'puan' => 10,
        'fiyat' => 19.99,
        'para_birimi' => 'TRY',
        'aktif' => false,
        'sira' => 3,
    ]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/odeme/paketler?platform=android')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.kod', 'kredi_25')
        ->assertJsonPath('data.0.magaza_urun_kodu', 'magmug.kredi25')
        ->assertJsonPath('data.1.kod', 'kredi_60')
        ->assertJsonPath('data.1.rozet', 'EN POPULER');
});

it('credits the correct package points when a purchase is verified', function () {
    $kullanici = User::factory()->create([
        'mevcut_puan' => 5,
    ]);

    PuanPaketi::query()->create([
        'kod' => 'kredi_60',
        'android_urun_kodu' => 'magmug.kredi60',
        'ios_urun_kodu' => 'magmug.kredi60.ios',
        'puan' => 60,
        'fiyat' => 99.99,
        'para_birimi' => 'TRY',
        'onerilen_mi' => true,
        'aktif' => true,
        'sira' => 1,
    ]);

    app()->instance(FisDogrulamaServisi::class, new class extends FisDogrulamaServisi {
        public function dogrula(string $platform, string $fisVerisi, string $urunKodu): array
        {
            return [
                'gecerli' => true,
                'islem_kodu' => 'order-123',
                'hata' => null,
            ];
        }
    });

    Sanctum::actingAs($kullanici);

    $this->postJson('/api/odeme/dogrula', [
        'platform' => 'android',
        'urun_kodu' => 'magmug.kredi60',
        'fis_verisi' => 'purchase-token-1',
        'tutar' => 99.99,
        'para_birimi' => 'TRY',
    ])->assertCreated()
        ->assertJsonPath('paket.kod', 'kredi_60')
        ->assertJsonPath('paket.puan', 60);

    expect($kullanici->fresh()->mevcut_puan)->toBe(65)
        ->and(Odeme::query()->where('islem_kodu', 'order-123')->exists())->toBeTrue();
});
