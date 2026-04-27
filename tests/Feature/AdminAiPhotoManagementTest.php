<?php

use App\Models\User;
use App\Models\UserFotografi;
use App\Services\Media\UserProfilePhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('creates an ai persona with optimized profile photos from the studio form', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);

    $payload = aiPhotoStudioPayload([
        'kullanici_adi' => 'lina_photo_ai',
        'fotograflar' => [
            UploadedFile::fake()->image('lina.jpg', 2200, 1800)->size(600),
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ai.kaydet'), $payload)
        ->assertRedirect();

    $user = User::query()->where('kullanici_adi', 'lina_photo_ai')->firstOrFail();
    $photo = UserFotografi::query()->where('user_id', $user->id)->firstOrFail();

    expect($photo->ana_fotograf_mi)->toBeTrue()
        ->and($photo->mime_tipi)->toBe('image/jpeg')
        ->and($photo->onizleme_yolu)->not->toBeNull()
        ->and($user->fresh()->profil_resmi)->toBe($photo->dosya_yolu);

    Storage::disk('public')->assertExists($photo->dosya_yolu);
    Storage::disk('public')->assertExists($photo->onizleme_yolu);

    [$width, $height] = getimagesize(Storage::disk('public')->path($photo->dosya_yolu));
    [$previewWidth, $previewHeight] = getimagesize(Storage::disk('public')->path($photo->onizleme_yolu));

    expect(max($width, $height))->toBeLessThanOrEqual(1600)
        ->and(max($previewWidth, $previewHeight))->toBeLessThanOrEqual(480);
});

it('uploads multiple photos from an ai detail or edit screen and keeps the first one primary', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $aiUser = User::factory()->aiKullanici()->create();

    $this->actingAs($admin)
        ->post(route('admin.ai.fotograflar.user-store', $aiUser), [
            'fotograflar' => [
                UploadedFile::fake()->image('first.jpg', 1200, 900)->size(300),
                UploadedFile::fake()->image('second.png', 900, 1200)->size(300),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('basari');

    $photos = $aiUser->fotograflar()->orderBy('sira_no')->get();

    expect($photos)->toHaveCount(2)
        ->and($photos->first()->ana_fotograf_mi)->toBeTrue()
        ->and($aiUser->fresh()->profil_resmi)->toBe($photos->first()->dosya_yolu);
});

it('bulk uploads photos to the selected ai user', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $aiUser = User::factory()->aiKullanici()->create();

    $this->actingAs($admin)
        ->post(route('admin.ai.fotograflar.store'), [
            'hedef_modu' => 'selected',
            'user_id' => $aiUser->id,
            'fotograflar' => [
                UploadedFile::fake()->image('bulk-one.jpg')->size(250),
                UploadedFile::fake()->image('bulk-two.png')->size(250),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('basari');

    expect($aiUser->fotograflar()->count())->toBe(2);
});

it('bulk assigns photos by filename and reports missing ai users', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $aiUser = User::factory()->aiKullanici()->create([
        'kullanici_adi' => 'target_ai',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ai.fotograflar.store'), [
            'hedef_modu' => 'filename',
            'fotograflar' => [
                UploadedFile::fake()->image('target_ai__1.jpg')->size(250),
                UploadedFile::fake()->image('missing_ai__1.jpg')->size(250),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('basari')
        ->assertSessionHas('hatalar');

    expect($aiUser->fotograflar()->count())->toBe(1);
});

it('enforces the six photo limit and promotes the next photo after deleting the primary', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['is_admin' => true]);
    $aiUser = User::factory()->aiKullanici()->create();
    $photoService = app(UserProfilePhotoService::class);

    foreach (range(1, 6) as $index) {
        $photoService->upload(
            $aiUser,
            UploadedFile::fake()->image("photo-{$index}.jpg", 800, 800)->size(120),
            $index === 1,
        );
    }

    $this->actingAs($admin)
        ->post(route('admin.ai.fotograflar.user-store', $aiUser), [
            'fotograflar' => [
                UploadedFile::fake()->image('extra.jpg')->size(120),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('hatalar');

    expect($aiUser->fotograflar()->count())->toBe(6);

    $primary = $aiUser->fotograflar()->where('ana_fotograf_mi', true)->firstOrFail();
    $next = $aiUser->fotograflar()->where('id', '!=', $primary->id)->orderBy('sira_no')->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('admin.ai.fotograflar.destroy', [$aiUser, $primary]))
        ->assertRedirect()
        ->assertSessionHas('basari');

    expect($aiUser->fresh()->profil_resmi)->toBe($next->fresh()->dosya_yolu)
        ->and($next->fresh()->ana_fotograf_mi)->toBeTrue()
        ->and($aiUser->fotograflar()->count())->toBe(5);
});

it('renders ai photo management controls in the studio ui', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $aiUser = User::factory()->aiKullanici()->create([
        'ad' => 'Aylin',
        'soyad' => 'Foto',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.ai.fotograflar'))
        ->assertOk()
        ->assertSeeText('AI fotograf yonetimi')
        ->assertSeeText('Toplu yukleme')
        ->assertSeeText('Aylin Foto');

    $this->actingAs($admin)
        ->get(route('admin.ai.ekle'))
        ->assertOk()
        ->assertSeeText('Profil Fotograflari')
        ->assertSee('name="fotograflar[]"', false);

    $this->actingAs($admin)
        ->get(route('admin.ai.goster', $aiUser))
        ->assertOk()
        ->assertSeeText('Profil galerisi')
        ->assertSeeText('Toplu panele ac');
});

function aiPhotoStudioPayload(array $overrides = []): array
{
    return array_merge([
        'ad' => 'Lina',
        'soyad' => 'Stone',
        'kullanici_adi' => 'lina_photo_ai',
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
