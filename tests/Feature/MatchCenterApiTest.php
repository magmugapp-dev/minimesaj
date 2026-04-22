<?php

use App\Models\Ayar;
use App\Models\Engelleme;
use App\Models\Eslesme;
use App\Models\PuanHareketi;
use App\Models\Sohbet;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::forget('ayar:eslesme_baslatma_maliyeti');
    Cache::forget('ayar:gunluk_ucretsiz_hak');
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

    User::factory()->create([
        'hesap_tipi' => 'ai',
        'cevrim_ici_mi' => false,
        'hesap_durumu' => 'pasif',
        'is_admin' => false,
    ]);

    User::factory()->create([
        'hesap_tipi' => 'user',
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
        'is_admin' => true,
    ]);
Sanctum::actingAs($kullanici);

    $this->getJson('/api/dating/eslesme-merkezi')
        ->assertOk()
        ->assertJsonPath('mevcut_puan', 27)
        ->assertJsonPath('gunluk_ucretsiz_hak', 3)
        ->assertJsonPath('eslesme_baslatma_maliyeti', 8)
        ->assertJsonPath('cevrimici_kisi_sayisi', 1)
        ->assertJsonPath('bekleyen_kisi_sayisi', 1)
        ->assertJsonPath('filtreler.cinsiyet', 'kadin')
        ->assertJsonPath('filtreler.yas', '26_35')
        ->assertJsonPath('filtreler.super_eslesme_aktif_mi', true);
});

it('updates the waiting person count according to match filters', function () {
    $kullanici = User::factory()->create([
        'eslesme_cinsiyet_filtresi' => 'tum',
        'eslesme_yas_filtresi' => 'tum',
    ]);

    User::factory()->create([
        'cinsiyet' => 'kadin',
        'dogum_yili' => now()->year - 24,
        'hesap_durumu' => 'aktif',
        'is_admin' => false,
    ]);

    User::factory()->create([
        'cinsiyet' => 'erkek',
        'dogum_yili' => now()->year - 24,
        'hesap_durumu' => 'aktif',
        'is_admin' => false,
    ]);

    User::factory()->create([
        'cinsiyet' => 'kadin',
        'dogum_yili' => now()->year - 32,
        'hesap_durumu' => 'aktif',
        'is_admin' => false,
    ]);

    Sanctum::actingAs($kullanici);

    $this->getJson('/api/dating/eslesme-merkezi')
        ->assertOk()
        ->assertJsonPath('bekleyen_kisi_sayisi', 3);

    $this->patchJson('/api/dating/eslesme-tercihleri', [
        'cinsiyet' => 'kadin',
        'yas' => '18_25',
        'super_eslesme_aktif_mi' => false,
    ])->assertOk();

    $this->getJson('/api/dating/eslesme-merkezi')
        ->assertOk()
        ->assertJsonPath('bekleyen_kisi_sayisi', 1);
});

it('prioritizes online candidates before falling back to the general user pool', function () {
    $kullanici = User::factory()->create([
        'mevcut_puan' => 30,
        'gunluk_ucretsiz_hak' => 2,
        'son_hak_yenileme_tarihi' => now(),
        'eslesme_cinsiyet_filtresi' => 'tum',
        'eslesme_yas_filtresi' => 'tum',
    ]);

    $cevrimDisiAday = User::factory()->create([
        'cevrim_ici_mi' => false,
        'hesap_durumu' => 'aktif',
        'is_admin' => false,
    ]);

    $cevrimiciAday = User::factory()->create([
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
        'is_admin' => false,
    ]);

    User::factory()->create([
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
        'is_admin' => true,
    ]);

    Sanctum::actingAs($kullanici);

    $this->getJson('/api/dating/eslesme-merkezi')
        ->assertOk()
        ->assertJsonPath('cevrimici_kisi_sayisi', 1)
        ->assertJsonPath('bekleyen_kisi_sayisi', 2);

    $this->postJson('/api/dating/eslesme-baslat')
        ->assertOk()
        ->assertJsonPath('durum', 'aday_bulundu')
        ->assertJsonPath('aday.id', $cevrimiciAday->id);

    $this->postJson("/api/dating/eslesme-gec/{$cevrimiciAday->id}")
        ->assertOk();

    $this->postJson('/api/dating/eslesme-baslat')
        ->assertOk()
        ->assertJsonPath('durum', 'aday_bulundu')
        ->assertJsonPath('aday.id', $cevrimDisiAday->id);
});

it('does not show manually skipped candidates again', function () {
    $kullanici = User::factory()->create([
        'mevcut_puan' => 30,
        'gunluk_ucretsiz_hak' => 1,
        'son_hak_yenileme_tarihi' => now(),
    ]);

    $gecilenAday = User::factory()->create([
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
    ]);

    Sanctum::actingAs($kullanici);

    $this->postJson("/api/dating/eslesme-gec/{$gecilenAday->id}")
        ->assertOk();

    $this->postJson('/api/dating/eslesme-baslat')
        ->assertOk()
        ->assertJsonPath('durum', 'aday_yok');
});

it('starts a conversation from a match candidate without requiring likes', function () {
    $kullanici = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);

    $aday = User::factory()->create([
        'hesap_durumu' => 'aktif',
        'is_admin' => false,
    ]);

    Sanctum::actingAs($kullanici);

    $response = $this->postJson("/api/dating/eslesme-sohbet/{$aday->id}")
        ->assertOk()
        ->assertJsonPath('durum', 'eslesme')
        ->assertJsonPath('mesaj', 'Sohbet hazir.');

    $eslesmeId = $response->json('eslesme_id');
    $sohbetId = $response->json('sohbet_id');

    expect($eslesmeId)->toBeInt()
        ->and($sohbetId)->toBeInt();

    $this->assertDatabaseHas('eslesmeler', [
        'id' => $eslesmeId,
        'user_id' => $kullanici->id,
        'eslesen_user_id' => $aday->id,
        'durum' => 'aktif',
    ]);

    $this->assertDatabaseHas('sohbetler', [
        'id' => $sohbetId,
        'eslesme_id' => $eslesmeId,
        'durum' => 'aktif',
    ]);
});

it('reuses an existing active match conversation when starting direct messaging', function () {
    $kullanici = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);

    $aday = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);

    $eslesme = Eslesme::query()->create([
        'user_id' => $aday->id,
        'eslesen_user_id' => $kullanici->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
        'baslatan_user_id' => $aday->id,
    ]);

    $sohbet = Sohbet::query()->create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
    ]);

    Sanctum::actingAs($kullanici);

    $this->postJson("/api/dating/eslesme-sohbet/{$aday->id}")
        ->assertOk()
        ->assertJsonPath('durum', 'eslesme')
        ->assertJsonPath('eslesme_id', $eslesme->id)
        ->assertJsonPath('sohbet_id', $sohbet->id);

    expect(Eslesme::query()->count())->toBe(1)
        ->and(Sohbet::query()->count())->toBe(1);
});

it('does not start a direct conversation when either side has blocked the other', function () {
    $kullanici = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);

    $aday = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);

    Engelleme::query()->create([
        'engelleyen_user_id' => $aday->id,
        'engellenen_user_id' => $kullanici->id,
    ]);

    Sanctum::actingAs($kullanici);

    $this->postJson("/api/dating/eslesme-sohbet/{$aday->id}")
        ->assertStatus(422)
        ->assertJsonPath('durum', 'engellendi');

    expect(Eslesme::query()->count())->toBe(0)
        ->and(Sohbet::query()->count())->toBe(0);
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
        'gunluk_ucretsiz_hak' => 0,
        'son_hak_yenileme_tarihi' => now(),
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
        ->assertJsonPath('gunluk_ucretsiz_hak', 0)
        ->assertJsonPath('ucretsiz_hak_kullanildi', false)
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
        'gunluk_ucretsiz_hak' => 0,
        'son_hak_yenileme_tarihi' => now(),
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
        ->assertJsonPath('gunluk_ucretsiz_hak', 0)
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
        'gunluk_ucretsiz_hak' => 0,
        'son_hak_yenileme_tarihi' => now(),
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
        ->assertJsonPath('gunluk_ucretsiz_hak', 0)
        ->assertJsonPath('gerekli_puan', 8)
        ->assertJsonPath('eksik_puan', 5);

    expect($kullanici->fresh()->mevcut_puan)->toBe(3);
});

it('consumes a daily free match right before spending points', function () {
    Ayar::query()->updateOrCreate(
        ['anahtar' => 'eslesme_baslatma_maliyeti'],
        ['deger' => '8', 'grup' => 'puan_sistemi', 'tip' => 'integer'],
    );
    Ayar::query()->updateOrCreate(
        ['anahtar' => 'gunluk_ucretsiz_hak'],
        ['deger' => '3', 'grup' => 'limitler', 'tip' => 'integer'],
    );

    $kullanici = User::factory()->create([
        'mevcut_puan' => 30,
        'gunluk_ucretsiz_hak' => 1,
        'son_hak_yenileme_tarihi' => now(),
    ]);

    $aday = User::factory()->create([
        'cevrim_ici_mi' => true,
        'hesap_durumu' => 'aktif',
    ]);

    Sanctum::actingAs($kullanici);

    $this->postJson('/api/dating/eslesme-baslat')
        ->assertOk()
        ->assertJsonPath('durum', 'aday_bulundu')
        ->assertJsonPath('aday.id', $aday->id)
        ->assertJsonPath('mevcut_puan', 30)
        ->assertJsonPath('gunluk_ucretsiz_hak', 0)
        ->assertJsonPath('ucretsiz_hak_kullanildi', true);

    expect($kullanici->fresh()->mevcut_puan)->toBe(30)
        ->and($kullanici->fresh()->gunluk_ucretsiz_hak)->toBe(0)
        ->and(PuanHareketi::query()->count())->toBe(0);
});
