<?php

use App\Models\Mesaj;
use App\Models\Eslesme;
use App\Models\Sohbet;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication to submit a dating report', function () {
    $this->postJson('/api/dating/sikayet', [
        'hedef_tipi' => 'user',
        'hedef_id' => 1,
        'kategori' => 'Sahte profil',
    ])->assertUnauthorized();
});

it('creates a user report for the authenticated user', function () {
    $sikayetEden = User::factory()->create();
    $hedefKullanici = User::factory()->create();

    Sanctum::actingAs($sikayetEden);

    $this->postJson('/api/dating/sikayet', [
        'hedef_tipi' => 'user',
        'hedef_id' => $hedefKullanici->id,
        'kategori' => 'Taciz veya zorbalik',
        'aciklama' => 'Rahatsiz edici mesajlar gonderiyor.',
    ])->assertCreated()
        ->assertJsonPath('data.hedef_tipi', 'user')
        ->assertJsonPath('data.hedef_id', $hedefKullanici->id)
        ->assertJsonPath('data.kategori', 'Taciz veya zorbalik');

    $this->assertDatabaseHas('sikayetler', [
        'sikayet_eden_user_id' => $sikayetEden->id,
        'hedef_tipi' => 'user',
        'hedef_id' => $hedefKullanici->id,
        'kategori' => 'Taciz veya zorbalik',
    ]);
});

it('creates a message report for the authenticated user', function () {
    $sikayetEden = User::factory()->create();
    $digerKullanici = User::factory()->create();
    $eslesme = Eslesme::create([
        'user_id' => $sikayetEden->id,
        'eslesen_user_id' => $digerKullanici->id,
        'eslesme_turu' => 'rastgele',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
    ]);
    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
    ]);
    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $digerKullanici->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Rahatsiz edici bir mesaj',
        'okundu_mu' => false,
        'silindi_mi' => false,
        'herkesten_silindi_mi' => false,
        'ai_tarafindan_uretildi_mi' => false,
    ]);

    Sanctum::actingAs($sikayetEden);

    $this->postJson('/api/dating/sikayet', [
        'hedef_tipi' => 'mesaj',
        'hedef_id' => $mesaj->id,
        'kategori' => 'Uygunsuz icerik',
        'aciklama' => 'Bu mesaj topluluk kurallarina aykiri.',
    ])->assertCreated()
        ->assertJsonPath('data.hedef_tipi', 'mesaj')
        ->assertJsonPath('data.hedef_id', $mesaj->id)
        ->assertJsonPath('data.kategori', 'Uygunsuz icerik');

    $this->assertDatabaseHas('sikayetler', [
        'sikayet_eden_user_id' => $sikayetEden->id,
        'hedef_tipi' => 'mesaj',
        'hedef_id' => $mesaj->id,
        'kategori' => 'Uygunsuz icerik',
    ]);
});
