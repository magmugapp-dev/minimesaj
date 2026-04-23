<?php

use App\Models\Hediye;
use App\Models\HediyeGonderimi;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

it('lists only active gifts for the mobile app', function () {
    $kullanici = User::factory()->create();

    Hediye::query()->create([
        'kod' => 'gul',
        'ad' => 'Gul',
        'ikon' => '🌹',
        'puan_bedeli' => 5,
        'aktif' => true,
        'sira' => 20,
    ]);
    Hediye::query()->create([
        'kod' => 'pasif',
        'ad' => 'Pasif',
        'ikon' => '🎁',
        'puan_bedeli' => 99,
        'aktif' => false,
        'sira' => 10,
    ]);

    Sanctum::actingAs($kullanici);

    $this->getJson('/api/hediyeler')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kod', 'gul')
        ->assertJsonPath('data.0.puan_bedeli', 5);
});

it('charges the panel defined gift cost while sending a gift', function () {
    Notification::fake();

    $gonderen = User::factory()->create(['mevcut_puan' => 100]);
    $alici = User::factory()->create();
    $hediye = Hediye::query()->create([
        'kod' => 'yuzuk',
        'ad' => 'Yuzuk',
        'ikon' => '💍',
        'puan_bedeli' => 37,
        'aktif' => true,
        'sira' => 10,
    ]);

    Sanctum::actingAs($gonderen);

    $this->postJson('/api/hediye/gonder', [
        'alici_user_id' => $alici->id,
        'hediye_id' => $hediye->id,
        'puan_degeri' => 1,
    ])->assertCreated()
        ->assertJsonPath('gonderim.hediye_id', $hediye->id)
        ->assertJsonPath('gonderim.puan_bedeli', 37)
        ->assertJsonPath('mevcut_puan', 63);

    $this->assertDatabaseHas('hediye_gonderimleri', [
        'gonderen_user_id' => $gonderen->id,
        'alici_user_id' => $alici->id,
        'hediye_id' => $hediye->id,
        'puan_bedeli' => 37,
    ]);
    $this->assertDatabaseHas('puan_hareketleri', [
        'user_id' => $gonderen->id,
        'islem_tipi' => 'harcama',
        'puan_miktari' => -37,
    ]);

    expect($gonderen->fresh()->mevcut_puan)->toBe(63);
});

it('shows gift sender profile data on the recipient profile', function () {
    $gonderen = User::factory()->create([
        'ad' => 'Ada',
        'soyad' => 'Yilmaz',
        'kullanici_adi' => 'ada',
        'profil_resmi' => 'profil/ada.jpg',
    ]);
    $alici = User::factory()->create();
    $bakan = User::factory()->create();
    $hediye = Hediye::query()->create([
        'kod' => 'kahve',
        'ad' => 'Kahve',
        'ikon' => '☕',
        'puan_bedeli' => 3,
        'aktif' => true,
        'sira' => 10,
    ]);

    HediyeGonderimi::query()->create([
        'gonderen_user_id' => $gonderen->id,
        'alici_user_id' => $alici->id,
        'hediye_id' => $hediye->id,
        'hediye_adi' => $hediye->ad,
        'puan_bedeli' => $hediye->puan_bedeli,
    ]);

    Sanctum::actingAs($bakan);

    $this->getJson("/api/dating/profil/{$alici->id}")
        ->assertOk()
        ->assertJsonPath('data.alinan_hediyeler.0.hediye_adi', 'Kahve')
        ->assertJsonPath('data.alinan_hediyeler.0.gonderen.ad', 'Ada')
        ->assertJsonPath('data.alinan_hediyeler.0.gonderen.kullanici_adi', 'ada');
});
