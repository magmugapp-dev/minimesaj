<?php

use App\Models\Engelleme;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function olusturSohbet(User $ilkKullanici, User $ikinciKullanici): Sohbet
{
    $eslesme = Eslesme::query()->create([
        'user_id' => $ilkKullanici->id,
        'eslesen_user_id' => $ikinciKullanici->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
        'baslatan_user_id' => $ilkKullanici->id,
    ]);

    return Sohbet::query()->create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
    ]);
}

it('prevents a blocked user from sending a chat message to the blocker', function () {
    $engelleyen = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);
    $engellenen = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);
    $sohbet = olusturSohbet($engelleyen, $engellenen);

    Engelleme::query()->create([
        'engelleyen_user_id' => $engelleyen->id,
        'engellenen_user_id' => $engellenen->id,
    ]);

    Sanctum::actingAs($engellenen);

    $this->postJson("/api/dating/sohbetler/{$sohbet->id}/mesajlar", [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Merhaba',
    ])->assertStatus(422)
        ->assertJsonPath('kod', 'engellendi')
        ->assertJsonPath('durum', 'engellendi')
        ->assertJsonPath('message', 'Bu kullanici sizi engelledi.');

    expect(Mesaj::query()->count())->toBe(0);
});

it('allows chat messages again after the block is removed', function () {
    $engelleyen = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);
    $engellenen = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);
    $sohbet = olusturSohbet($engelleyen, $engellenen);

    Engelleme::query()->create([
        'engelleyen_user_id' => $engelleyen->id,
        'engellenen_user_id' => $engellenen->id,
    ]);

    Sanctum::actingAs($engelleyen);
    $this->deleteJson("/api/dating/engelle/{$engellenen->id}")
        ->assertOk();

    Sanctum::actingAs($engellenen);

    $this->postJson("/api/dating/sohbetler/{$sohbet->id}/mesajlar", [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Tekrar yazabiliyorum',
    ])->assertCreated()
        ->assertJsonPath('data.mesaj_metni', 'Tekrar yazabiliyorum');

    expect(Mesaj::query()->count())->toBe(1)
        ->and(Mesaj::query()->first()?->gonderen_user_id)->toBe($engellenen->id);
});
