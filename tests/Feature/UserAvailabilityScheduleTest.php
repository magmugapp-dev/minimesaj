<?php

use App\Models\AiAyar;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

it('stores availability schedules from the ai studio form', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.ai.kaydet'), scheduleStudioPayload([
        'kullanici_adi' => 'takvimli_ai_form',
        'availability_schedules' => [
            scheduleRow(now()->addDay()->toDateString(), '09:00', '12:00', 'active'),
            scheduleRow(now()->addDay()->toDateString(), '13:00', '15:30', 'passive'),
        ],
    ]));

    $response->assertRedirect();

    $user = User::query()->where('kullanici_adi', 'takvimli_ai_form')->firstOrFail();
    $schedules = $user->availabilitySchedules()->orderBy('specific_date')->orderBy('starts_at')->get();

    expect($schedules)->toHaveCount(2)
        ->and($schedules[0]->specific_date?->toDateString())->toBe(now()->addDay()->toDateString())
        ->and($schedules[0]->starts_at)->toBe('09:00:00')
        ->and($schedules[0]->status)->toBe('active')
        ->and($schedules[1]->status)->toBe('passive');
});

it('rejects overlapping availability schedules in the ai studio form', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->from(route('admin.ai.ekle'))
        ->actingAs($admin)
        ->post(route('admin.ai.kaydet'), scheduleStudioPayload([
            'kullanici_adi' => 'cakisma_ai_form',
            'availability_schedules' => [
                scheduleRow(now()->addDay()->toDateString(), '09:00', '12:00', 'active'),
                scheduleRow(now()->addDay()->toDateString(), '11:30', '13:00', 'passive'),
            ],
        ]))
        ->assertRedirect(route('admin.ai.ekle'))
        ->assertSessionHasErrors(['availability_schedules.1.starts_at']);
});

it('returns online status fields from the dating profile api', function () {
    $viewer = User::factory()->create([
        'hesap_durumu' => 'aktif',
    ]);

    $aiUser = User::factory()->aiKullanici()->create([
        'kullanici_adi' => 'api_takvim_ai',
        'cevrim_ici_mi' => true,
    ]);

    AiAyar::query()->create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-3.1-auto-quality',
        'minimum_cevap_suresi_saniye' => 4,
        'maksimum_cevap_suresi_saniye' => 24,
        'saat_dilimi' => 'Europe/Istanbul',
        'uyku_baslangic' => '23:00',
        'uyku_bitis' => '07:00',
    ]);

    $aiUser->availabilitySchedules()->createMany([
        [
            'recurrence_type' => 'date',
            'specific_date' => '2026-04-26',
            'starts_at' => '10:00:00',
            'ends_at' => '11:00:00',
            'status' => 'active',
        ],
        [
            'recurrence_type' => 'date',
            'specific_date' => '2026-04-26',
            'starts_at' => '10:15:00',
            'ends_at' => '10:45:00',
            'status' => 'passive',
        ],
    ]);

    Sanctum::actingAs($viewer);

    Carbon::setTestNow('2026-04-26 10:05:00');
    $this->getJson("/api/dating/profil/{$aiUser->id}")
        ->assertOk()
        ->assertJsonPath('data.cevrim_ici_mi', true)
        ->assertJsonPath('data.isOnline', true)
        ->assertJsonPath('data.onlineStatusReason', 'active_schedule');

    Carbon::setTestNow('2026-04-26 10:30:00');
    $this->getJson("/api/dating/profil/{$aiUser->id}")
        ->assertOk()
        ->assertJsonPath('data.cevrim_ici_mi', false)
        ->assertJsonPath('data.isOnline', false)
        ->assertJsonPath('data.onlineStatusReason', 'passive_schedule');

    Carbon::setTestNow('2026-04-26 12:30:00');
    $this->getJson("/api/dating/profil/{$aiUser->id}")
        ->assertOk()
        ->assertJsonPath('data.cevrim_ici_mi', true)
        ->assertJsonPath('data.isOnline', true)
        ->assertJsonPath('data.onlineStatusReason', 'default');

    Carbon::setTestNow();
});

function scheduleStudioPayload(array $overrides = []): array
{
    $payload = array_merge([
        'ad' => 'Takvim',
        'soyad' => 'Persona',
        'kullanici_adi' => 'takvimli_ai',
        'hesap_durumu' => 'aktif',
        'cinsiyet' => 'kadin',
        'dogum_yili' => 1996,
        'biyografi' => 'Takvimli aktiflik kullanan bir AI persona.',
        'ulke' => 'Turkiye',
        'model_adi' => 'gemini-3.1-auto-quality',
        'aktif_mi' => '1',
        'dating_aktif_mi' => '1',
        'instagram_aktif_mi' => '1',
        'ilk_mesaj_atar_mi' => '1',
        'ilk_mesaj_tonu' => 'Rahat bir acilis ister.',
        'persona_ozeti' => 'Gunluk akisini takvimle yoneten sicak bir persona.',
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
        'gunluk_rutin' => 'Sabah kahve, oglen cekim, aksam topluluk etkilesimi.',
        'hobiler' => 'Kahve, gezi rotalari.',
        'sevdigi_mekanlar' => 'Sahil kafeleri.',
        'aile_arkadas_notu' => 'Yakin bir sosyal cevresi var.',
        'iliski_gecmisi_tonu' => 'Temkinli ama acik',
        'konusma_imzasi' => 'Kisa ve enerjik cumleler.',
        'cevap_ritmi' => 'Hizli',
        'emoji_aliskanligi' => 'Yerinde kullanir',
        'kacinilacak_persona_detaylari' => 'Asiri resmi durmasin.',
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
        'blocked_topics' => "nefret dili\nsaldirganlik",
        'required_rules' => "dogal kal\ntek mesaj tek niyet",
        'availability_schedules' => [],
    ], $overrides);

    foreach (array_keys(config('ai_studio_dropdowns.behavior_sliders', [])) as $field) {
        $payload[$field] = $payload[$field] ?? 5;
    }

    return $payload;
}

function scheduleRow(string $date, string $start, string $end, string $status): array
{
    return [
        'specific_date' => $date,
        'starts_at' => $start,
        'ends_at' => $end,
        'status' => $status,
    ];
}
