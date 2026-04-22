<?php

use App\Models\User;
use App\Models\Ayar;
use App\Services\AyarServisi;
use App\Services\Kimlik\Sosyal\SosyalKimlikBilgisi;
use App\Services\Kimlik\Sosyal\SosyalKimlikSaglayici;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('authenticates an existing apple user with social login', function () {
    $user = User::factory()->create([
        'apple_kimlik' => 'apple-existing-user',
        'email' => 'apple@example.com',
    ]);

    sahteSosyalSaglayici('social.provider.apple', new SosyalKimlikBilgisi(
        provider: 'apple',
        providerUserId: 'apple-existing-user',
        email: 'apple@example.com',
        emailVerified: true,
        displayName: 'Apple User',
    ));

    $response = $this->postJson('/api/auth/sosyal/giris', [
        'provider' => 'apple',
        'token' => 'apple-code',
        'istemci_tipi' => 'dating',
        'ad' => 'Apple',
        'soyad' => 'User',
    ]);

    $response->assertOk()
        ->assertJsonPath('durum', 'authenticated')
        ->assertJsonPath('kullanici.id', $user->id)
        ->assertJsonStructure(['token']);
});

it('authenticates an existing google user with social login', function () {
    $user = User::factory()->create([
        'google_kimlik' => 'google-existing-user',
        'email' => 'google@example.com',
    ]);

    sahteSosyalSaglayici('social.provider.google', new SosyalKimlikBilgisi(
        provider: 'google',
        providerUserId: 'google-existing-user',
        email: 'google@example.com',
        emailVerified: true,
        displayName: 'Google User',
    ));

    $response = $this->postJson('/api/auth/sosyal/giris', [
        'provider' => 'google',
        'token' => 'google-token',
        'istemci_tipi' => 'dating',
    ]);

    $response->assertOk()
        ->assertJsonPath('durum', 'authenticated')
        ->assertJsonPath('kullanici.id', $user->id)
        ->assertJsonStructure(['token']);
});

it('links a social provider to an existing account by verified email', function () {
    $user = User::factory()->create([
        'email' => 'bagla@example.com',
        'google_kimlik' => null,
    ]);

    sahteSosyalSaglayici('social.provider.google', new SosyalKimlikBilgisi(
        provider: 'google',
        providerUserId: 'google-link-user',
        email: 'bagla@example.com',
        emailVerified: true,
        displayName: 'Baglanan Kullanici',
    ));

    $response = $this->postJson('/api/auth/sosyal/giris', [
        'provider' => 'google',
        'token' => 'google-token',
        'istemci_tipi' => 'dating',
    ]);

    $response->assertOk()
        ->assertJsonPath('durum', 'authenticated')
        ->assertJsonPath('kullanici.id', $user->id);

    expect($user->fresh()->google_kimlik)->toBe('google-link-user');
});

it('returns onboarding required for a new social user', function () {
    sahteSosyalSaglayici('social.provider.apple', new SosyalKimlikBilgisi(
        provider: 'apple',
        providerUserId: 'apple-new-user',
        email: 'yeni@example.com',
        emailVerified: true,
        displayName: 'Yeni Kullanici',
    ));

    $response = $this->postJson('/api/auth/sosyal/giris', [
        'provider' => 'apple',
        'token' => 'apple-code',
        'istemci_tipi' => 'dating',
        'ad' => 'Yeni',
        'soyad' => 'Kullanici',
    ]);

    $response->assertOk()
        ->assertJsonPath('durum', 'onboarding_required')
        ->assertJsonStructure(['social_session', 'prefill']);
});

it('rejects social registration when the username is already taken', function () {
    User::factory()->create([
        'kullanici_adi' => 'takenuser',
    ]);

    sahteSosyalSaglayici('social.provider.google', new SosyalKimlikBilgisi(
        provider: 'google',
        providerUserId: 'google-register-user',
        email: 'register@example.com',
        emailVerified: true,
        displayName: 'Kayit Kullanici',
    ));

    $socialSession = sosyalOturumBaslat($this, 'google');

    $response = $this->postJson('/api/auth/sosyal/kayit', [
        'social_session' => $socialSession,
        'ad' => 'Kayit Kullanici',
        'kullanici_adi' => 'takenuser',
        'cinsiyet' => 'erkek',
        'dogum_yili' => 1998,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('kullanici_adi');
});

it('rejects social login for inactive users', function () {
    User::factory()->create([
        'google_kimlik' => 'inactive-google-user',
        'hesap_durumu' => 'pasif',
    ]);

    sahteSosyalSaglayici('social.provider.google', new SosyalKimlikBilgisi(
        provider: 'google',
        providerUserId: 'inactive-google-user',
        email: 'inactive@example.com',
        emailVerified: true,
        displayName: 'Pasif Kullanici',
    ));

    $response = $this->postJson('/api/auth/sosyal/giris', [
        'provider' => 'google',
        'token' => 'google-token',
        'istemci_tipi' => 'dating',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('provider');
});

it('registers a new social user and uploads the profile photo', function () {
    Storage::fake('public');
    Ayar::query()->updateOrCreate(
        ['anahtar' => 'kayit_puani'],
        ['deger' => '100', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Kayit Bonusu'],
    );
    app(AyarServisi::class)->onbellekTemizle();

    sahteSosyalSaglayici('social.provider.google', new SosyalKimlikBilgisi(
        provider: 'google',
        providerUserId: 'google-photo-user',
        email: 'photo@example.com',
        emailVerified: true,
        displayName: 'Fotolu Kullanici',
    ));

    $socialSession = sosyalOturumBaslat($this, 'google');

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/auth/sosyal/kayit', [
            'social_session' => $socialSession,
            'ad' => 'Fotolu Kullanici',
            'kullanici_adi' => 'fotolukullanici',
            'cinsiyet' => 'kadin',
            'dogum_yili' => 1996,
            'dosya' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $response->assertCreated()
        ->assertJsonPath('durum', 'authenticated')
        ->assertJsonPath('kullanici.kullanici_adi', 'fotolukullanici')
        ->assertJsonPath('kullanici.mevcut_puan', 100)
        ->assertJsonStructure(['token']);

    $user = User::where('google_kimlik', 'google-photo-user')->first();

    expect($user)->not->toBeNull();
    expect($user?->profil_resmi)->not->toBeNull();

    Storage::disk('public')->assertExists($user->profil_resmi);
    $this->assertDatabaseHas('user_fotograflari', [
        'user_id' => $user->id,
        'ana_fotograf_mi' => true,
    ]);
});

it('uses the google avatar as the profile image when registration photo is skipped', function () {
    $avatarUrl = 'https://lh3.googleusercontent.com/a/google-avatar';

    sahteSosyalSaglayici('social.provider.google', new SosyalKimlikBilgisi(
        provider: 'google',
        providerUserId: 'google-avatar-user',
        email: 'avatar@example.com',
        emailVerified: true,
        displayName: 'Avatar Kullanici',
        avatarUrl: $avatarUrl,
    ));

    $socialSession = sosyalOturumBaslat($this, 'google');

    $response = $this->postJson('/api/auth/sosyal/kayit', [
        'social_session' => $socialSession,
        'ad' => 'Avatar Kullanici',
        'kullanici_adi' => 'avatarkullanici',
        'cinsiyet' => 'kadin',
        'dogum_yili' => 1996,
    ]);

    $response->assertCreated()
        ->assertJsonPath('durum', 'authenticated')
        ->assertJsonPath('kullanici.profil_resmi', $avatarUrl);

    $user = User::where('google_kimlik', 'google-avatar-user')->first();

    expect($user)->not->toBeNull();
    expect($user?->profil_resmi)->toBe($avatarUrl);
});

function sahteSosyalSaglayici(string $anahtar, SosyalKimlikBilgisi $kimlik): void
{
    app()->instance($anahtar, new class($kimlik) implements SosyalKimlikSaglayici
    {
        public function __construct(private SosyalKimlikBilgisi $kimlik) {}

        public function dogrula(array $veri): SosyalKimlikBilgisi
        {
            return $this->kimlik;
        }
    });
}

function sosyalOturumBaslat(Tests\TestCase $testCase, string $provider): string
{
    $response = $testCase->postJson('/api/auth/sosyal/giris', [
        'provider' => $provider,
        'token' => $provider . '-token',
        'istemci_tipi' => 'dating',
        'ad' => 'Kayit',
        'soyad' => 'Kullanici',
    ]);

    return $response->json('social_session');
}
