<?php

use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sikayet;
use App\Models\Sohbet;
use App\Models\User;

function adminSikayetTestKullanicisi(): User
{
    return User::factory()->create([
        'is_admin' => true,
    ]);
}

it('shows message target details on the admin complaint detail page', function () {
    $admin = adminSikayetTestKullanicisi();
    $sikayetEden = User::factory()->create([
        'ad' => 'Ayse',
        'soyad' => 'Yilmaz',
    ]);
    $gonderen = User::factory()->create([
        'ad' => 'Mehmet',
        'soyad' => 'Kaya',
    ]);

    $eslesme = Eslesme::create([
        'user_id' => $sikayetEden->id,
        'eslesen_user_id' => $gonderen->id,
        'eslesme_turu' => 'rastgele',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
    ]);

    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
    ]);

    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $gonderen->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Bu mesaj admin detayinda gorunmeli.',
        'okundu_mu' => false,
        'silindi_mi' => false,
        'herkesten_silindi_mi' => false,
        'ai_tarafindan_uretildi_mi' => false,
    ]);

    $sikayet = Sikayet::create([
        'sikayet_eden_user_id' => $sikayetEden->id,
        'hedef_tipi' => 'mesaj',
        'hedef_id' => $mesaj->id,
        'kategori' => 'Uygunsuz icerik',
        'aciklama' => 'Icerik kurallara aykiri.',
        'durum' => 'bekliyor',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.moderasyon.sikayetler.goster', $sikayet))
        ->assertOk()
        ->assertSeeText('Bu mesaj admin detayinda gorunmeli.')
        ->assertSeeText('Mehmet Kaya')
        ->assertSee(route('admin.kullanicilar.goster', $gonderen), false)
        ->assertSee(route('admin.eslesmeler.sohbet', $eslesme), false);
});

it('shows message preview on the admin complaint list page', function () {
    $admin = adminSikayetTestKullanicisi();
    $sikayetEden = User::factory()->create([
        'ad' => 'Zeynep',
        'soyad' => 'Demir',
    ]);
    $gonderen = User::factory()->create([
        'ad' => 'Kerem',
        'soyad' => 'Aydin',
    ]);

    $eslesme = Eslesme::create([
        'user_id' => $sikayetEden->id,
        'eslesen_user_id' => $gonderen->id,
        'eslesme_turu' => 'rastgele',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
    ]);

    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
    ]);

    $mesaj = Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $gonderen->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Bu onizleme sikayet listesinde de gorunmeli.',
        'okundu_mu' => false,
        'silindi_mi' => false,
        'herkesten_silindi_mi' => false,
        'ai_tarafindan_uretildi_mi' => false,
    ]);

    Sikayet::create([
        'sikayet_eden_user_id' => $sikayetEden->id,
        'hedef_tipi' => 'mesaj',
        'hedef_id' => $mesaj->id,
        'kategori' => 'Taciz veya zorbalik',
        'aciklama' => 'Liste onizlemesi kontrol ediliyor.',
        'durum' => 'bekliyor',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.moderasyon.sikayetler'))
        ->assertOk()
        ->assertSeeText('Bu onizleme sikayet listesinde de gorunmeli.')
        ->assertSeeText('Kerem Aydin');
});
