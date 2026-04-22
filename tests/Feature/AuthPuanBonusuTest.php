<?php

use App\Models\Ayar;
use App\Models\PuanHareketi;
use App\Models\User;
use App\Services\AyarServisi;
use Illuminate\Support\Facades\Hash;

it('awards registration bonus points on classic registration', function () {
    Ayar::query()->updateOrCreate(
        ['anahtar' => 'kayit_puani'],
        ['deger' => '40', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Kayit Bonusu'],
    );
    app(AyarServisi::class)->onbellekTemizle();

    $response = $this->postJson('/api/auth/kayit', [
        'ad' => 'Test Kullanici',
        'kullanici_adi' => 'kayitbonus',
        'email' => 'kayitbonus@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'istemci_tipi' => 'dating',
    ]);

    $response->assertCreated()
        ->assertJsonPath('kullanici.mevcut_puan', 40)
        ->assertJsonPath('kullanici.gunluk_ucretsiz_hak', 3);

    $user = User::query()->where('kullanici_adi', 'kayitbonus')->firstOrFail();

    expect($user->mevcut_puan)->toBe(40);
    $this->assertDatabaseHas('puan_hareketleri', [
        'user_id' => $user->id,
        'islem_tipi' => 'yonetici',
        'referans_tipi' => 'kayit_bonusu',
        'puan_miktari' => 40,
    ]);
});

it('awards daily login bonus once per day on login', function () {
    Ayar::query()->updateOrCreate(
        ['anahtar' => 'gunluk_giris_puani'],
        ['deger' => '15', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Gunluk Giris Bonusu'],
    );
    app(AyarServisi::class)->onbellekTemizle();

    $user = User::factory()->create([
        'kullanici_adi' => 'dailybonus',
        'password' => Hash::make('secret123'),
        'mevcut_puan' => 0,
        'son_gunluk_giris_puani_tarihi' => null,
    ]);

    $this->postJson('/api/auth/giris', [
        'kullanici_adi' => 'dailybonus',
        'password' => 'secret123',
        'istemci_tipi' => 'dating',
    ])->assertOk()
        ->assertJsonPath('kullanici.mevcut_puan', 15);

    $this->postJson('/api/auth/giris', [
        'kullanici_adi' => 'dailybonus',
        'password' => 'secret123',
        'istemci_tipi' => 'dating',
    ])->assertOk();

    expect($user->fresh()->mevcut_puan)->toBe(15)
        ->and(
            PuanHareketi::query()
                ->where('user_id', $user->id)
                ->where('islem_tipi', 'yonetici')
                ->where('referans_tipi', 'gunluk_giris_bonusu')
                ->count()
        )
        ->toBe(1);
});

it('awards daily login bonus on me bootstrap when not already awarded today', function () {
    Ayar::query()->updateOrCreate(
        ['anahtar' => 'gunluk_giris_puani'],
        ['deger' => '12', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Gunluk Giris Bonusu'],
    );
    app(AyarServisi::class)->onbellekTemizle();

    $user = User::factory()->create([
        'mevcut_puan' => 5,
        'son_gunluk_giris_puani_tarihi' => null,
    ]);
    $token = $user->createToken('dating', ['dating'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/auth/ben')
        ->assertOk();

    expect($user->fresh()->mevcut_puan)->toBe(17);
});
