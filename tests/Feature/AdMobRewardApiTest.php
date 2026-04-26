<?php

use App\Models\Ayar;
use App\Models\PuanHareketi;
use App\Models\ReklamOdulu;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    foreach ([
        'admob_aktif_mi',
        'reklam_odulu',
        'reklam_gunluk_odul_limiti',
    ] as $anahtar) {
        Cache::forget('ayar:' . $anahtar);
    }
});

it('updates admob settings from the admin panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->put(route('admin.ayarlar.kategori.guncelle', ['kategori' => 'admob']), [
            'admob_aktif_mi' => '1',
            'admob_test_modu' => '0',
            'admob_android_app_id' => 'ca-app-pub-1111111111111111~2222222222',
            'admob_ios_app_id' => 'ca-app-pub-3333333333333333~4444444444',
            'admob_android_rewarded_unit_id' => 'ca-app-pub-1111111111111111/5555555555',
            'admob_ios_rewarded_unit_id' => 'ca-app-pub-3333333333333333/6666666666',
            'admob_android_match_native_unit_id' => 'ca-app-pub-1111111111111111/7777777777',
            'admob_ios_match_native_unit_id' => 'ca-app-pub-3333333333333333/8888888888',
        ])
        ->assertRedirect();

    expect(Ayar::query()->where('anahtar', 'admob_aktif_mi')->value('deger'))->toBe('1')
        ->and(Ayar::query()->where('anahtar', 'admob_test_modu')->value('deger'))->toBe('0')
        ->and(Ayar::query()->where('anahtar', 'admob_android_rewarded_unit_id')->value('deger'))
        ->toBe('ca-app-pub-1111111111111111/5555555555');
});

it('returns rewarded ad status for the current user', function () {
    configureAdMobRewardSettings(reward: 23, limit: 2);

    $user = User::factory()->create();
    ReklamOdulu::query()->create([
        'user_id' => $user->id,
        'olay_kodu' => 'reward-event-1',
        'reklam_platformu' => 'android',
        'reklam_birim_kodu' => 'ca-app-pub-test/rewarded',
        'reklam_tipi' => 'rewarded',
        'odul_tipi' => 'puan',
        'odul_miktari' => 23,
        'dogrulandi_mi' => true,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/odeme/reklam-odul/durum')
        ->assertOk()
        ->assertJsonPath('aktif_mi', true)
        ->assertJsonPath('odul_puani', 23)
        ->assertJsonPath('gunluk_limit', 2)
        ->assertJsonPath('bugun_izlenen', 1)
        ->assertJsonPath('kalan_hak', 1);
});

it('credits the configured reward points after a rewarded ad callback', function () {
    configureAdMobRewardSettings(reward: 23, limit: 10);

    $user = User::factory()->create(['mevcut_puan' => 4]);
    Sanctum::actingAs($user);

    $this->postJson('/api/odeme/reklam-odul', [
        'reklam_platformu' => 'android',
        'reklam_birim_kodu' => 'ca-app-pub-test/rewarded',
        'olay_kodu' => 'reward-event-1',
    ])
        ->assertCreated()
        ->assertJsonPath('odul_puani', 23)
        ->assertJsonPath('mevcut_puan', 27)
        ->assertJsonPath('kalan_hak', 9)
        ->assertJsonPath('tekrar_mi', false);

    $odul = ReklamOdulu::query()->first();
    expect($user->fresh()->mevcut_puan)->toBe(27)
        ->and($odul)->not->toBeNull()
        ->and($odul->reklam_tipi)->toBe('rewarded')
        ->and($odul->odul_tipi)->toBe('puan')
        ->and($odul->odul_miktari)->toBe(23)
        ->and($odul->dogrulandi_mi)->toBeTrue();

    $hareket = PuanHareketi::query()->where('user_id', $user->id)->first();
    expect($hareket)->not->toBeNull()
        ->and($hareket->islem_tipi)->toBe('reklam')
        ->and($hareket->puan_miktari)->toBe(23)
        ->and($hareket->referans_tipi)->toBe('reklam_odulu')
        ->and($hareket->referans_id)->toBe($odul->id);
});

it('does not credit the same reward event twice', function () {
    configureAdMobRewardSettings(reward: 12, limit: 10);

    $user = User::factory()->create(['mevcut_puan' => 0]);
    Sanctum::actingAs($user);

    $payload = [
        'reklam_platformu' => 'ios',
        'reklam_birim_kodu' => 'ca-app-pub-test/ios-rewarded',
        'olay_kodu' => 'same-reward-event',
    ];

    $this->postJson('/api/odeme/reklam-odul', $payload)->assertCreated();
    $this->postJson('/api/odeme/reklam-odul', $payload)
        ->assertOk()
        ->assertJsonPath('tekrar_mi', true)
        ->assertJsonPath('mevcut_puan', 12);

    expect($user->fresh()->mevcut_puan)->toBe(12)
        ->and(ReklamOdulu::query()->count())->toBe(1)
        ->and(PuanHareketi::query()->count())->toBe(1);
});

it('rejects new rewarded ad claims after the daily limit', function () {
    configureAdMobRewardSettings(reward: 9, limit: 1);

    $user = User::factory()->create(['mevcut_puan' => 0]);
    Sanctum::actingAs($user);

    $this->postJson('/api/odeme/reklam-odul', [
        'reklam_platformu' => 'android',
        'reklam_birim_kodu' => 'ca-app-pub-test/rewarded',
        'olay_kodu' => 'reward-event-1',
    ])->assertCreated();

    $this->postJson('/api/odeme/reklam-odul', [
        'reklam_platformu' => 'android',
        'reklam_birim_kodu' => 'ca-app-pub-test/rewarded',
        'olay_kodu' => 'reward-event-2',
    ])
        ->assertStatus(429)
        ->assertJsonPath('kalan_hak', 0)
        ->assertJsonPath('bugun_izlenen', 1);

    expect($user->fresh()->mevcut_puan)->toBe(9)
        ->and(ReklamOdulu::query()->count())->toBe(1)
        ->and(PuanHareketi::query()->count())->toBe(1);
});

function configureAdMobRewardSettings(int $reward, int $limit): void
{
    foreach ([
        'admob_aktif_mi' => ['deger' => '1', 'grup' => 'admob', 'tip' => 'boolean'],
        'reklam_odulu' => ['deger' => (string) $reward, 'grup' => 'puan_sistemi', 'tip' => 'integer'],
        'reklam_gunluk_odul_limiti' => ['deger' => (string) $limit, 'grup' => 'puan_sistemi', 'tip' => 'integer'],
    ] as $anahtar => $alanlar) {
        Ayar::query()->updateOrCreate(['anahtar' => $anahtar], $alanlar);
        Cache::forget('ayar:' . $anahtar);
    }
}
