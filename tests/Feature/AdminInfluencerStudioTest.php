<?php

use App\Models\AiAyar;
use App\Models\AiPersonaProfile;
use App\Models\InstagramHesap;
use App\Models\User;

it('creates a new influencer persona from the studio form', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.influencer.kaydet'), influencerStudioPayload([
        'kullanici_adi' => 'burcin_influencer_form',
        'instagram_kullanici_adi' => 'burcinprofile',
    ]));

    $response->assertRedirect();

    $user = User::query()->where('kullanici_adi', 'burcin_influencer_form')->firstOrFail();
    $persona = AiPersonaProfile::query()->where('ai_user_id', $user->id)->firstOrFail();
    $legacy = AiAyar::query()->where('user_id', $user->id)->firstOrFail();
    $instagram = InstagramHesap::query()->where('user_id', $user->id)->firstOrFail();
    $schedule = $user->availabilitySchedules()->first();

    expect($user->hesap_tipi)->toBe('ai')
        ->and($persona->instagram_aktif_mi)->toBeTrue()
        ->and(data_get($persona->metadata, 'model_adi'))->toBe('gemini-3.1-auto-quality')
        ->and($legacy->model_adi)->toBe('gemini-3.1-auto-quality')
        ->and($instagram->instagram_kullanici_adi)->toBe('burcinprofile')
        ->and($instagram->otomatik_cevap_aktif_mi)->toBeTrue()
        ->and($schedule)->not->toBeNull();
});

it('imports influencer users from json', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $payload = influencerStudioPayload([
        'ad' => 'Lara',
        'soyad' => 'Kent',
        'kullanici_adi' => 'lara_influencer_json',
        'instagram_kullanici_adi' => 'laraprofile',
        'dating_aktif_mi' => false,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.influencer.json-kaydet'), [
        'json_veri' => json_encode([$payload], JSON_UNESCAPED_UNICODE),
    ]);

    $response
        ->assertRedirect(route('admin.influencer.index'))
        ->assertSessionHasNoErrors();

    $user = User::query()->where('kullanici_adi', 'lara_influencer_json')->firstOrFail();

    expect(AiPersonaProfile::query()->where('ai_user_id', $user->id)->exists())->toBeTrue()
        ->and(InstagramHesap::query()->where('user_id', $user->id)->where('instagram_kullanici_adi', 'laraprofile')->exists())->toBeTrue();
});

it('deletes an influencer and cascades instagram data', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $influencer = User::factory()->aiKullanici()->create([
        'kullanici_adi' => 'silinecek_influencer',
    ]);

    $instagram = InstagramHesap::factory()->create([
        'user_id' => $influencer->id,
        'instagram_kullanici_adi' => 'silinecekprofile',
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.influencer.sil', $influencer));

    $response
        ->assertRedirect(route('admin.influencer.index'))
        ->assertSessionHasNoErrors();

    expect(User::query()->whereKey($influencer->id)->exists())->toBeFalse()
        ->and(InstagramHesap::query()->whereKey($instagram->id)->exists())->toBeFalse();
});

it('deletes an ai persona from the admin panel', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $aiUser = User::factory()->aiKullanici()->create([
        'kullanici_adi' => 'silinecek_ai_persona',
    ]);

    $response = $this->actingAs($admin)->delete(route('admin.ai.sil', $aiUser));

    $response
        ->assertRedirect(route('admin.ai.index'))
        ->assertSessionHasNoErrors();

    expect(User::query()->whereKey($aiUser->id)->exists())->toBeFalse();
});

function influencerStudioPayload(array $overrides = []): array
{
    return array_merge([
        'ad' => 'Burcin',
        'soyad' => 'Evci',
        'kullanici_adi' => 'burcin_influencer',
        'hesap_durumu' => 'aktif',
        'cinsiyet' => 'kadin',
        'dogum_yili' => 1998,
        'biyografi' => 'Moda, sehir ve kahve uzerine icerik ureten sicak bir persona.',
        'ulke' => 'Turkiye',
        'model_adi' => 'gemini-3.1-auto-quality',
        'aktif_mi' => '1',
        'dating_aktif_mi' => '0',
        'instagram_aktif_mi' => '1',
        'ilk_mesaj_atar_mi' => '1',
        'ilk_mesaj_tonu' => 'Hizli, sicak ve merakli bir acilis.',
        'persona_ozeti' => 'Kameraya yakin, enerjik ve dogal hissettiren bir influencer karakteri.',
        'ana_dil_kodu' => 'tr',
        'ikinci_diller' => ['Ingilizce'],
        'persona_ulke' => 'Turkiye',
        'persona_bolge' => 'Marmara',
        'persona_sehir' => 'Istanbul',
        'persona_mahalle' => 'Sahil semti',
        'kulturel_koken' => 'Anadolu',
        'uyruk' => 'Turkiye',
        'yasam_tarzi' => 'Sosyal',
        'meslek' => 'Icerik ureticisi',
        'sektor' => 'Medya',
        'egitim' => 'Lisans',
        'okul_bolum' => 'Iletisim',
        'yas_araligi' => '23-27',
        'gunluk_rutin' => 'Sabah spor, gun boyu cekim ve aksam topluluk etkilesimi.',
        'hobiler' => 'Kahve, cekim, gezi rotalari.',
        'sevdigi_mekanlar' => 'Sahil kafeleri ve yaratıcı studiolar.',
        'aile_arkadas_notu' => 'Kalabalik ama guvendigi bir sosyal cevresi var.',
        'iliski_gecmisi_tonu' => 'Florte acik ama secici',
        'konusma_imzasi' => 'Kisa ama enerjik cumleler kullanir.',
        'cevap_ritmi' => 'Hizli',
        'emoji_aliskanligi' => 'Yerinde kullanir',
        'kacinilacak_persona_detaylari' => 'Asiri resmi ve uzak durmasin.',
        'konusma_tonu' => 'samimi',
        'konusma_stili' => 'akici',
        'mesaj_uzunlugu_min' => 18,
        'mesaj_uzunlugu_max' => 160,
        'minimum_cevap_suresi_saniye' => 4,
        'maksimum_cevap_suresi_saniye' => 24,
        'saat_dilimi' => 'Europe/Istanbul',
        'uyku_baslangic' => '01:00',
        'uyku_bitis' => '08:00',
        'hafta_sonu_uyku_baslangic' => '02:00',
        'hafta_sonu_uyku_bitis' => '09:30',
        'availability_schedules' => [
            [
                'specific_date' => now()->addDay()->toDateString(),
                'starts_at' => '09:00',
                'ends_at' => '12:00',
                'status' => 'active',
            ],
        ],
        'blocked_topics' => "nefret dili\nsaldirganlik",
        'required_rules' => "dogal kal\ntek mesaj tek niyet",
        'instagram_kullanici_adi' => 'burcinprofile',
        'instagram_profil_id' => '17840000000000000',
        'otomatik_cevap_aktif_mi' => '1',
        'yarim_otomatik_mod_aktif_mi' => '0',
        'instagram_hesap_aktif_mi' => '1',
    ], $overrides);
}
