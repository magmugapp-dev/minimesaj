<?php

use App\Models\AiAyar;
use App\Models\AiPersonaProfile;
use App\Models\User;
use App\Services\YapayZeka\V2\AiPersonaService;

it('creates a new ai persona from the v2 studio form', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $payload = studioPayload([
        'kullanici_adi' => 'lina_studio_ai',
    ]);

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
        ->and(data_get($persona->metadata, 'model_adi'))->toBe('gemini-3.1-auto-quality')
        ->and($legacy->model_adi)->toBe('gemini-3.1-auto-quality')
        ->and($legacy->mizah_seviyesi)->toBe(6)
        ->and($legacy->kiskanclik_seviyesi)->toBe(6);
});

it('defaults missing behavior sliders when creating a new ai persona from the v2 studio form', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.ai.kaydet'), studioPayload([
        'ad' => 'Mila',
        'soyad' => 'Hart',
        'kullanici_adi' => 'mila_defaults_ai',
    ]));

    $response->assertRedirect();

    $user = User::query()->where('kullanici_adi', 'mila_defaults_ai')->firstOrFail();
    $persona = AiPersonaProfile::query()->where('ai_user_id', $user->id)->firstOrFail();
    $legacy = AiAyar::query()->where('user_id', $user->id)->firstOrFail();

    expect($persona->mizah_seviyesi)->toBe(5)
        ->and($persona->argo_seviyesi)->toBe(2)
        ->and($persona->sicaklik_seviyesi)->toBe(6)
        ->and($legacy->mizah_seviyesi)->toBe(5)
        ->and($legacy->flort_seviyesi)->toBe(4);
});

it('preserves existing behavior sliders when update payload omits them', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);
    $aiUser = User::factory()->aiKullanici()->create([
        'ad' => 'Selin',
        'soyad' => 'Kor',
        'kullanici_adi' => 'selin_existing_ai',
    ]);

    $persona = app(AiPersonaService::class)->ensureForUser($aiUser);
    $persona->update([
        'mizah_seviyesi' => 9,
        'argo_seviyesi' => 7,
        'sicaklik_seviyesi' => 8,
        'kiskanclik_seviyesi' => 1,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.ai.guncelle', $aiUser), studioPayload([
        'ad' => $aiUser->ad,
        'soyad' => $aiUser->soyad,
        'kullanici_adi' => $aiUser->kullanici_adi,
        'cinsiyet' => $aiUser->cinsiyet,
        'dogum_yili' => $aiUser->dogum_yili,
        'biyografi' => $aiUser->biyografi,
        'ulke' => 'Turkiye',
        'ana_dil_kodu' => 'tr',
        'persona_ulke' => 'Turkiye',
        'persona_bolge' => 'Marmara',
        'persona_sehir' => 'Istanbul',
        'uyruk' => 'Turkiye',
        'saat_dilimi' => 'Europe/Istanbul',
    ]));

    $response
        ->assertRedirect(route('admin.ai.goster', $aiUser))
        ->assertSessionHasNoErrors();

    $persona->refresh();
    $legacy = AiAyar::query()->where('user_id', $aiUser->id)->firstOrFail();

    expect($persona->mizah_seviyesi)->toBe(9)
        ->and($persona->argo_seviyesi)->toBe(7)
        ->and($persona->sicaklik_seviyesi)->toBe(8)
        ->and($persona->kiskanclik_seviyesi)->toBe(1)
        ->and($legacy->mizah_seviyesi)->toBe(9)
        ->and($legacy->kiskanclik_seviyesi)->toBe(1);
});

it('rejects dropdown values outside the v2 catalog', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $payload = studioPayload([
        'ad' => 'Aria',
        'soyad' => null,
        'kullanici_adi' => 'aria_invalid_ai',
        'ana_dil_kodu' => 'tr',
        'persona_ulke' => 'Almanya',
        'persona_bolge' => 'Bati Almanya',
        'persona_sehir' => 'Berlin',
        'uyruk' => 'Almanya',
        'meslek' => 'Astronot',
    ]);

    foreach (array_keys(config('ai_studio_dropdowns.behavior_sliders')) as $field) {
        $payload[$field] = 5;
    }

    $this->from(route('admin.ai.ekle'))
        ->actingAs($admin)
        ->post(route('admin.ai.kaydet'), $payload)
        ->assertRedirect(route('admin.ai.ekle'))
        ->assertSessionHasErrors(['meslek']);
});

it('imports keyed json ai persona records without relying on numeric indexes', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $payload = [
        'persona_one' => [
            'ad' => 'Nora',
            'soyad' => 'Json',
            'kullanici_adi' => 'nora_keyed_json_ai',
            'cinsiyet' => 'kadin',
            'saglayici_tipi' => 'gemini',
            'model_adi' => 'gemini-3.1-auto-quality',
            'biyografi' => 'JSON import karakteri.',
        ],
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.ai.json-kaydet'), [
            'json_veri' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

    $response
        ->assertRedirect(route('admin.ai.index'))
        ->assertSessionHas('basari');

    expect(User::query()
        ->where('kullanici_adi', 'nora_keyed_json_ai')
        ->where('hesap_tipi', 'ai')
        ->exists())->toBeTrue();
});

function studioPayload(array $overrides = []): array
{
    return array_merge([
        'ad' => 'Lina',
        'soyad' => 'Stone',
        'kullanici_adi' => 'lina_studio_ai',
        'hesap_durumu' => 'aktif',
        'cinsiyet' => 'kadin',
        'dogum_yili' => 1998,
        'biyografi' => 'Sohbet etmeyi seven kozmopolit bir karakter.',
        'ulke' => 'Almanya',
        'model_adi' => 'gemini-3.1-auto-quality',
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
        'persona_mahalle' => 'Yaratici studyo mahallesi',
        'kulturel_koken' => 'Bati Avrupa',
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
    ], $overrides);
}
