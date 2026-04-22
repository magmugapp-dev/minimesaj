<?php

use App\Models\Ayar;
use App\Models\Begeni;
use App\Models\PuanHareketi;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::forget('ayar:eslesme_baslatma_maliyeti');
});

it('returns the match center summary together with persisted preferences', function () {
    Ayar::query()->create([
        'anahtar' => 'eslesme_baslatma_maliyeti',
        'deger' => '8',
        'grup' => 'puan_sistemi',
        'tip' => 'integer',
    ]);

    $kullanici = User::factory()->create([
        'mevcut_puan' => 27,
        'eslesme_cinsiyet_filtresi' => 'kadin',
        'eslesme_yas_filtresi' => '26_35',
        'super_eslesme_aktif_mi' => true,
    ]);

    $aday = User::factory()->create([
        'cinsiyet' => 'kadin',
        'dogum_yili' => now()->year - 28,
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
    ]);

    User::factory()->create([
        'cinsiyet' => 'erkek',
        'dogum_yili' => now()->year - 28,
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
    ]);

    User::factory()->create([
        'cinsiyet' => 'kadin',
        'dogum_yili' => now()->year - 22,
        'cevrim_ici_mi' => false,
        'hesap_durumu' => 'aktif',
    ]);

    Begeni::query()->create([
        'begenen_user_id' => $aday->id,
        'begenilen_user_id' => $kullanici->id,
        'eslesmeye_donustu_mu' => false,
    ]);

    Sanctum::actingAs($kullanici);

    $this->getJson('/api/dating/eslesme-merkezi')
        ->assertOk()
        ->assertJsonPath('mevcut_puan', 27)
        ->assertJsonPath('eslesme_baslatma_maliyeti', 8)
        ->assertJsonPath('cevrimici_kisi_sayisi', 1)
        ->assertJsonPath('bekleyen_begeni_sayisi', 1)
        ->assertJsonPath('filtreler.cinsiyet', 'kadin')
        ->assertJsonPath('filtreler.yas', '26_35')
        ->assertJsonPath('filtreler.super_eslesme_aktif_mi', true);
});

it('persists updated match preferences', function () {
    $kullanici = User::factory()->create([
        'eslesme_cinsiyet_filtresi' => 'tum',
        'eslesme_yas_filtresi' => 'tum',
        'super_eslesme_aktif_mi' => false,
    ]);

    Sanctum::actingAs($kullanici);

    $this->patchJson('/api/dating/eslesme-tercihleri', [
        'cinsiyet' => 'erkek',
        'yas' => '36_ustu',
        'super_eslesme_aktif_mi' => true,
    ])->assertOk()
        ->assertJsonPath('filtreler.cinsiyet', 'erkek')
        ->assertJsonPath('filtreler.yas', '36_ustu')
        ->assertJsonPath('filtreler.super_eslesme_aktif_mi', true);

    expect($kullanici->fresh()->eslesme_cinsiyet_filtresi)->toBe('erkek')
        ->and($kullanici->fresh()->eslesme_yas_filtresi)->toBe('36_ustu')
        ->and($kullanici->fresh()->super_eslesme_aktif_mi)->toBeTrue();
});

it('starts a match, returns a filtered online candidate, and spends points once', function () {
    Ayar::query()->create([
        'anahtar' => 'eslesme_baslatma_maliyeti',
        'deger' => '8',
        'grup' => 'puan_sistemi',
        'tip' => 'integer',
    ]);

    $kullanici = User::factory()->create([
        'mevcut_puan' => 30,
        'eslesme_cinsiyet_filtresi' => 'kadin',
        'eslesme_yas_filtresi' => '26_35',
        'super_eslesme_aktif_mi' => false,
    ]);

    $uygunAday = User::factory()->create([
        'cinsiyet' => 'kadin',
        'dogum_yili' => now()->year - 29,
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
    ]);

    User::factory()->create([
        'cinsiyet' => 'erkek',
        'dogum_yili' => now()->year - 29,
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
    ]);

    User::factory()->create([
        'cinsiyet' => 'kadin',
        'dogum_yili' => now()->year - 22,
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
    ]);

    User::factory()->create([
        'cinsiyet' => 'kadin',
        'dogum_yili' => now()->year - 29,
        'cevrim_ici_mi' => false,
        'hesap_durumu' => 'aktif',
    ]);

    Sanctum::actingAs($kullanici);

    $this->postJson('/api/dating/eslesme-baslat')
        ->assertOk()
        ->assertJsonPath('durum', 'aday_bulundu')
        ->assertJsonPath('aday.id', $uygunAday->id)
        ->assertJsonPath('mevcut_puan', 22)
        ->assertJsonPath('eslesme_baslatma_maliyeti', 8);

    expect($kullanici->fresh()->mevcut_puan)->toBe(22);

    $this->assertDatabaseHas('puan_hareketleri', [
        'user_id' => $kullanici->id,
        'islem_tipi' => 'harcama',
        'puan_miktari' => -8,
        'referans_tipi' => 'user',
        'referans_id' => $uygunAday->id,
    ]);
});

it('does not spend points when no online candidate matches the filters', function () {
    Ayar::query()->create([
        'anahtar' => 'eslesme_baslatma_maliyeti',
        'deger' => '8',
        'grup' => 'puan_sistemi',
        'tip' => 'integer',
    ]);

    $kullanici = User::factory()->create([
        'mevcut_puan' => 30,
        'eslesme_cinsiyet_filtresi' => 'kadin',
        'eslesme_yas_filtresi' => '26_35',
    ]);

    User::factory()->create([
        'cinsiyet' => 'erkek',
        'dogum_yili' => now()->year - 29,
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
    ]);

    User::factory()->create([
        'cinsiyet' => 'kadin',
        'dogum_yili' => now()->year - 21,
        'cevrim_ici_mi' => false,
        'hesap_durumu' => 'aktif',
    ]);

    Sanctum::actingAs($kullanici);

    $this->postJson('/api/dating/eslesme-baslat')
        ->assertOk()
        ->assertJsonPath('durum', 'aday_yok')
        ->assertJsonPath('mevcut_puan', 30)
        ->assertJsonPath('eslesme_baslatma_maliyeti', 8);

    expect($kullanici->fresh()->mevcut_puan)->toBe(30)
        ->and(PuanHareketi::query()->count())->toBe(0);
});

it('returns a 402 response with point details when balance is insufficient', function () {
    Ayar::query()->create([
        'anahtar' => 'eslesme_baslatma_maliyeti',
        'deger' => '8',
        'grup' => 'puan_sistemi',
        'tip' => 'integer',
    ]);

    $kullanici = User::factory()->create([
        'mevcut_puan' => 3,
    ]);

    User::factory()->create([
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
    ]);

    Sanctum::actingAs($kullanici);

    $this->postJson('/api/dating/eslesme-baslat')
        ->assertStatus(402)
        ->assertJsonPath('durum', 'yetersiz_puan')
        ->assertJsonPath('mevcut_puan', 3)
        ->assertJsonPath('gerekli_puan', 8)
        ->assertJsonPath('eksik_puan', 5);

    expect($kullanici->fresh()->mevcut_puan)->toBe(3);
});
