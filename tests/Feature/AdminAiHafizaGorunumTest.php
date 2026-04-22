<?php

use App\Models\AiHafiza;
use App\Models\Eslesme;
use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use App\Models\InstagramMesaj;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use Illuminate\Support\Carbon;

function adminKullaniciOlustur(): User
{
    return User::factory()->create([
        'is_admin' => true,
    ]);
}

it('shows active instagram memories on the person message detail page', function () {
    Carbon::setTestNow('2026-04-09 19:00:00');

    $admin = adminKullaniciOlustur();
    $aiUser = User::factory()->aiKullanici()->create();

    $hesap = InstagramHesap::create([
        'user_id' => $aiUser->id,
        'instagram_kullanici_adi' => 'ai.panel',
    ]);

    $kisi = InstagramKisi::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => 'target-1',
        'kullanici_adi' => 'real.person',
        'gorunen_ad' => 'Real Person',
    ]);

    InstagramMesaj::create([
        'instagram_hesap_id' => $hesap->id,
        'instagram_kisi_id' => $kisi->id,
        'gonderen_tipi' => 'karsi_taraf',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Merhaba!',
    ]);

    AiHafiza::create([
        'ai_user_id' => $aiUser->id,
        'hedef_tipi' => AiHafiza::HEDEF_TIPI_INSTAGRAM_KISI,
        'hedef_id' => $kisi->id,
        'hafiza_tipi' => AiHafiza::HAFIZA_TIPI_BILGI,
        'konu_anahtari' => 'memleket',
        'icerik' => 'Izmirli.',
        'onem_puani' => 7,
    ]);

    AiHafiza::create([
        'ai_user_id' => $aiUser->id,
        'hedef_tipi' => AiHafiza::HEDEF_TIPI_INSTAGRAM_KISI,
        'hedef_id' => $kisi->id,
        'hafiza_tipi' => AiHafiza::HAFIZA_TIPI_DUYGU,
        'konu_anahtari' => 'duygu_genel',
        'icerik' => 'Suresi dolmus duygu.',
        'onem_puani' => 5,
        'son_kullanma_tarihi' => now()->subHour(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.instagram.mesajlar', [$hesap, $kisi]))
        ->assertOk()
        ->assertSeeText('Real Person')
        ->assertSeeText('Izmirli.')
        ->assertDontSeeText('Suresi dolmus duygu.');

    Carbon::setTestNow();
});

it('shows match memory page for the clicked person using the counterpart ai memory', function () {
    $admin = adminKullaniciOlustur();
    $aiUser = User::factory()->aiKullanici()->create([
        'ad' => 'Aylin',
        'soyad' => 'AI',
    ]);
    $realUser = User::factory()->create([
        'ad' => 'Mert',
        'soyad' => 'Yilmaz',
    ]);

    $eslesme = Eslesme::create([
        'user_id' => $aiUser->id,
        'eslesen_user_id' => $realUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
    ]);

    AiHafiza::create([
        'ai_user_id' => $aiUser->id,
        'hedef_tipi' => AiHafiza::HEDEF_TIPI_USER,
        'hedef_id' => $realUser->id,
        'hafiza_tipi' => AiHafiza::HAFIZA_TIPI_BILGI,
        'konu_anahtari' => 'yasadigi_sehir',
        'icerik' => 'Ankarada yasiyor.',
        'onem_puani' => 8,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.eslesmeler.kisi-hafiza', [$eslesme, $realUser]))
        ->assertOk()
        ->assertSeeText('Mert Yilmaz')
        ->assertSeeText('Aylin AI')
        ->assertSeeText('Ankarada yasiyor.')
        ->assertSee(route('admin.kullanicilar.goster', $realUser), false);
});

it('shows memory summaries and memory links on match detail and chat pages', function () {
    $admin = adminKullaniciOlustur();
    $aiUser = User::factory()->aiKullanici()->create([
        'ad' => 'Selin',
        'soyad' => 'Bot',
    ]);
    $realUser = User::factory()->create([
        'ad' => 'Kerem',
        'soyad' => 'Demir',
    ]);

    $eslesme = Eslesme::create([
        'user_id' => $aiUser->id,
        'eslesen_user_id' => $realUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
    ]);

    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
        'toplam_mesaj_sayisi' => 1,
        'durum' => 'aktif',
        'son_mesaj_tarihi' => now(),
    ]);

    Mesaj::create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $realUser->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Selam!',
    ]);

    AiHafiza::create([
        'ai_user_id' => $aiUser->id,
        'hedef_tipi' => AiHafiza::HEDEF_TIPI_USER,
        'hedef_id' => $realUser->id,
        'hafiza_tipi' => AiHafiza::HAFIZA_TIPI_TERCIH,
        'konu_anahtari' => 'tercih_hobi',
        'icerik' => 'Yuruyus yapmayi seviyor.',
        'onem_puani' => 6,
    ]);

    $hafizaUrl = route('admin.eslesmeler.kisi-hafiza', [$eslesme, $realUser]);
    $profilUrl = route('admin.kullanicilar.goster', $realUser);

    $this->actingAs($admin)
        ->get(route('admin.eslesmeler.goster', $eslesme))
        ->assertOk()
        ->assertSeeText('Kisi Bazli AI Hafizasi')
        ->assertSeeText('Yuruyus yapmayi seviyor.')
        ->assertSee($hafizaUrl, false)
        ->assertSee($profilUrl, false);

    $this->actingAs($admin)
        ->get(route('admin.eslesmeler.sohbet', $eslesme))
        ->assertOk()
        ->assertSeeText('Yuruyus yapmayi seviyor.')
        ->assertSee($hafizaUrl, false)
        ->assertSee($profilUrl, false);
});

it('shows an empty state on the match memory page when there is no ai side', function () {
    $admin = adminKullaniciOlustur();
    $ilkKullanici = User::factory()->create();
    $ikinciKullanici = User::factory()->create();

    $eslesme = Eslesme::create([
        'user_id' => $ilkKullanici->id,
        'eslesen_user_id' => $ikinciKullanici->id,
        'eslesme_turu' => 'rastgele',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.eslesmeler.kisi-hafiza', [$eslesme, $ilkKullanici]))
        ->assertOk()
        ->assertSeeText('Bu kisi hakkinda aktif AI hafizasi bulunmuyor.');
});
