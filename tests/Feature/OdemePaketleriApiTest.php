<?php

use App\Models\Odeme;
use App\Models\PuanPaketi;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Services\FisDogrulamaServisi;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::forget('ayar:google_play_odeme_aktif_mi');
    Cache::forget('ayar:apple_odeme_aktif_mi');
    Cache::forget('ayar:google_play_paket_adi');
    Cache::forget('ayar:google_play_service_account_path');
});

function hazirGooglePlayKanali(): void
{
    Storage::disk('local')->put('ayarlar/google/test-service-account.json', '{}');

    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_paket_adi'],
        ['deger' => 'com.magmug.app', 'grup' => 'google_play', 'tip' => 'string', 'aciklama' => 'Google Play Paket Adı'],
    );

    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_service_account_path'],
        ['deger' => 'ayarlar/google/test-service-account.json', 'grup' => 'google_play', 'tip' => 'file', 'aciklama' => 'Google Play Service Account JSON'],
    );
}

it('lists only active point packages for the requested platform', function () {
    hazirGooglePlayKanali();

    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_odeme_aktif_mi'],
        ['deger' => '1', 'grup' => 'google_play', 'tip' => 'boolean', 'aciklama' => 'Google Play odemeleri aktif mi'],
    );

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

it('still lists point packages when the requested mobile payment channel is disabled', function () {
    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_odeme_aktif_mi'],
        ['deger' => '0', 'grup' => 'google_play', 'tip' => 'boolean', 'aciklama' => 'Google Play odemeleri aktif mi'],
    );

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

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/odeme/paketler?platform=android')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kod', 'kredi_25')
        ->assertJsonPath('data.0.magaza_urun_kodu', 'magmug.kredi25');
});

it('still lists point packages when the requested mobile payment channel is active but not ready', function () {
    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_odeme_aktif_mi'],
        ['deger' => '1', 'grup' => 'google_play', 'tip' => 'boolean', 'aciklama' => 'Google Play odemeleri aktif mi'],
    );

    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_paket_adi'],
        ['deger' => '', 'grup' => 'google_play', 'tip' => 'string', 'aciklama' => 'Google Play Paket Adı'],
    );

    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_service_account_path'],
        ['deger' => '', 'grup' => 'google_play', 'tip' => 'file', 'aciklama' => 'Google Play Service Account JSON'],
    );

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

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/odeme/paketler?platform=android')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kod', 'kredi_25')
        ->assertJsonPath('data.0.magaza_urun_kodu', 'magmug.kredi25');
});

it('lists active subscription packages for the requested platform', function () {
    \App\Models\AbonelikPaketi::query()->delete();

    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_odeme_aktif_mi'],
        ['deger' => '0', 'grup' => 'google_play', 'tip' => 'boolean', 'aciklama' => 'Google Play odemeleri aktif mi'],
    );

    \App\Models\AbonelikPaketi::query()->create([
        'kod' => 'premium_1_ay',
        'android_urun_kodu' => 'magmug.premium.1ay',
        'ios_urun_kodu' => 'magmug.premium.1ay.ios',
        'sure_ay' => 1,
        'fiyat' => 149.99,
        'para_birimi' => 'TRY',
        'aktif' => true,
        'sira' => 1,
    ]);

    \App\Models\AbonelikPaketi::query()->create([
        'kod' => 'premium_pasif',
        'android_urun_kodu' => 'magmug.premium.passive',
        'ios_urun_kodu' => 'magmug.premium.passive.ios',
        'sure_ay' => 12,
        'fiyat' => 999.99,
        'para_birimi' => 'TRY',
        'aktif' => false,
        'sira' => 2,
    ]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/odeme/abonelik-paketler?platform=android')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kod', 'premium_1_ay')
        ->assertJsonPath('data.0.magaza_urun_kodu', 'magmug.premium.1ay')
        ->assertJsonPath('data.0.urun_tipi', 'abonelik');
});

it('credits the correct package points when a purchase is verified', function () {
    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_odeme_aktif_mi'],
        ['deger' => '1', 'grup' => 'google_play', 'tip' => 'boolean', 'aciklama' => 'Google Play odemeleri aktif mi'],
    );

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
        public function __construct()
        {
            parent::__construct(app(\App\Services\Odeme\MobilOdemeAyarServisi::class));
        }

        public function dogrula(string $platform, string $fisVerisi, string $urunKodu, string $urunTipi = 'tek_seferlik'): array
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

it('does not credit points when the requested mobile payment channel is disabled', function () {
    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_odeme_aktif_mi'],
        ['deger' => '0', 'grup' => 'google_play', 'tip' => 'boolean', 'aciklama' => 'Google Play odemeleri aktif mi'],
    );

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

    Sanctum::actingAs($kullanici);

    $this->postJson('/api/odeme/dogrula', [
        'platform' => 'android',
        'urun_kodu' => 'magmug.kredi60',
        'fis_verisi' => 'purchase-token-1',
        'tutar' => 99.99,
        'para_birimi' => 'TRY',
    ])->assertStatus(422)
        ->assertJsonPath('mesaj', 'Secilen mobil odeme kanali panelde pasif durumda.');

    expect($kullanici->fresh()->mevcut_puan)->toBe(5);
});

it('activates subscription packages through verified google play subscription purchases', function () {
    \App\Models\Ayar::query()->updateOrCreate(
        ['anahtar' => 'google_play_odeme_aktif_mi'],
        ['deger' => '1', 'grup' => 'google_play', 'tip' => 'boolean', 'aciklama' => 'Google Play odemeleri aktif mi'],
    );

    $kullanici = User::factory()->create([
        'premium_aktif_mi' => false,
        'premium_bitis_tarihi' => null,
    ]);

    \App\Models\AbonelikPaketi::query()->updateOrCreate([
        'kod' => 'premium_1_ay',
    ], [
        'kod' => 'premium_1_ay',
        'android_urun_kodu' => 'magmug.premium.1ay',
        'ios_urun_kodu' => 'magmug.premium.1ay.ios',
        'sure_ay' => 1,
        'fiyat' => 149.99,
        'para_birimi' => 'TRY',
        'aktif' => true,
        'sira' => 1,
    ]);

    app()->instance(FisDogrulamaServisi::class, new class extends FisDogrulamaServisi {
        public function __construct()
        {
            parent::__construct(app(\App\Services\Odeme\MobilOdemeAyarServisi::class));
        }

        public function dogrula(string $platform, string $fisVerisi, string $urunKodu, string $urunTipi = 'tek_seferlik'): array
        {
            return [
                'gecerli' => true,
                'islem_kodu' => 'subscription-order-1',
                'hata' => null,
            ];
        }
    });

    Sanctum::actingAs($kullanici);

    $this->postJson('/api/odeme/dogrula', [
        'platform' => 'android',
        'urun_kodu' => 'magmug.premium.1ay',
        'urun_tipi' => 'abonelik',
        'fis_verisi' => 'purchase-token-subscription',
        'tutar' => 149.99,
        'para_birimi' => 'TRY',
    ])->assertCreated()
        ->assertJsonPath('paket.kod', 'premium_1_ay')
        ->assertJsonPath('paket.urun_tipi', 'abonelik')
        ->assertJsonPath('paket.sure_ay', 1);

    expect($kullanici->fresh()->premium_aktif_mi)->toBeTrue()
        ->and($kullanici->fresh()->premium_bitis_tarihi)->not->toBeNull();
});
