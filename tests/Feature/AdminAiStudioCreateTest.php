<?php

use App\Models\AiAyar;
use App\Models\AiPersonaProfile;
use App\Models\User;
it('creates a new ai persona from the v2 studio form', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $payload = [
        'ad' => 'Lina',
        'soyad' => 'Stone',
        'kullanici_adi' => 'lina_studio_ai',
        'hesap_durumu' => 'aktif',
        'cinsiyet' => 'kadin',
        'dogum_yili' => 1998,
        'biyografi' => 'Sohbet etmeyi seven kozmopolit bir karakter.',
        'ulke' => 'Almanya',
        'model_adi' => 'gemini-2.5-flash',
        'aktif_mi' => '1',
        'dating_aktif_mi' => '1',
        'instagram_aktif_mi' => '1',
        'ilk_mesaj_atar_mi' => '1',
        'ilk_mesaj_tonu' => 'Rahat ve merakli bir acilis ister.',
        'persona_ozeti' => 'Berlin merkezli, sicak ama zeki bir karakter.',
        'ana_dil_kodu' => 'de',
        'ikinci_diller' => ['Ingilizce', 'Turkce'],
        'persona_ulke' => 'Almanya',
        'persona_bolge' => 'Bati Almanya',
        'persona_sehir' => 'Berlin',
        'persona_mahalle' => 'Mitte',
        'kulturel_koken' => 'Avrupa',
        'uyruk' => 'Almanya',
        'yasam_tarzi' => 'Sehirli',
        'meslek' => 'Grafik tasarimci',
        'sektor' => 'Tasarim',
        'egitim' => 'Lisans',
        'okul_bolum' => 'Gorsel iletisim',
        'yas_araligi' => '28-32',
        'gunluk_rutin' => 'Sabah kahve, gun icinde tasarim, aksam yuruyus.',
        'hobiler' => 'Sergiler, caz, fotograf.',
        'sevdigi_mekanlar' => 'Kitapci kafeler ve nehir kenari.',
        'aile_arkadas_notu' => 'Kucuk ama yakin bir cevresi var.',
        'iliski_gecmisi_tonu' => 'Temkinli ama acik',
        'konusma_imzasi' => 'Kisa ama dusunceli cumleler.',
        'cevap_ritmi' => 'Dengeli',
        'emoji_aliskanligi' => 'Yerinde kullanir',
        'kacinilacak_persona_detaylari' => 'Sert ve kaba gozukmesin.',
        'konusma_tonu' => 'sicak',
        'konusma_stili' => 'akici',
        'mesaj_uzunlugu_min' => 24,
        'mesaj_uzunlugu_max' => 180,
        'minimum_cevap_suresi_saniye' => 3,
        'maksimum_cevap_suresi_saniye' => 21,
        'saat_dilimi' => 'Europe/Berlin',
        'uyku_baslangic' => '00:30',
        'uyku_bitis' => '08:00',
        'hafta_sonu_uyku_baslangic' => '01:30',
        'hafta_sonu_uyku_bitis' => '09:30',
        'blocked_topics' => "siddet\nnefret dili",
        'required_rules' => "dogal kal\ntek mesaj tek niyet",
    ];

    foreach (array_keys(config('ai_studio_dropdowns.behavior_sliders')) as $field) {
        $payload[$field] = 6;
    }

    $response = $this->actingAs($admin)->post(route('admin.ai.kaydet'), $payload);

    $response
        ->assertRedirect();

    $user = User::query()->where('kullanici_adi', 'lina_studio_ai')->firstOrFail();
    $persona = AiPersonaProfile::query()->where('ai_user_id', $user->id)->firstOrFail();
    $legacy = AiAyar::query()->where('user_id', $user->id)->firstOrFail();

    expect($user->hesap_tipi)->toBe('ai')
        ->and($user->dil)->toBe('de')
        ->and($persona->ana_dil_adi)->toBe('Almanca')
        ->and($persona->meslek)->toBe('Grafik tasarimci')
        ->and(data_get($persona->metadata, 'model_adi'))->toBe('gemini-2.5-flash')
        ->and($legacy->model_adi)->toBe('gemini-2.5-flash')
        ->and($legacy->mizah_seviyesi)->toBe(6)
        ->and($legacy->kiskanclik_seviyesi)->toBe(6);
});

it('rejects dropdown values outside the v2 catalog', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $payload = [
        'ad' => 'Aria',
        'kullanici_adi' => 'aria_invalid_ai',
        'hesap_durumu' => 'aktif',
        'cinsiyet' => 'kadin',
        'model_adi' => 'gemini-2.5-flash',
        'ana_dil_kodu' => 'tr',
        'meslek' => 'Astronot',
        'mesaj_uzunlugu_min' => 24,
        'mesaj_uzunlugu_max' => 180,
        'minimum_cevap_suresi_saniye' => 3,
        'maksimum_cevap_suresi_saniye' => 21,
    ];

    foreach (array_keys(config('ai_studio_dropdowns.behavior_sliders')) as $field) {
        $payload[$field] = 5;
    }

    $this->from(route('admin.ai.ekle'))
        ->actingAs($admin)
        ->post(route('admin.ai.kaydet'), $payload)
        ->assertRedirect(route('admin.ai.ekle'))
        ->assertSessionHasErrors(['meslek']);
});
