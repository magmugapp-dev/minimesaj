<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ad' => fake('tr_TR')->firstName(),
            'soyad' => fake('tr_TR')->lastName(),
            'kullanici_adi' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'hesap_tipi' => 'user',
            'hesap_durumu' => 'aktif',
            'dogum_yili' => fake()->numberBetween(1985, 2004),
            'cinsiyet' => fake()->randomElement(['erkek', 'kadin']),
            'ulke' => 'Türkiye',
            'il' => fake('tr_TR')->city(),
            'biyografi' => fake('tr_TR')->sentence(8),
            'mevcut_puan' => fake()->numberBetween(0, 500),
            'gunluk_ucretsiz_hak' => 3,
            'remember_token' => Str::random(10),
        ];
    }

    public function aiKullanici(): static
    {
        return $this->state(function () {
            $firstName = fake('tr_TR')->firstName('female');
            $lastName = fake('tr_TR')->lastName();
            $username = fake()->unique()->userName();
            $language = fake()->randomElement(['tr', 'en']);
            $matchAgeFilter = fake()->randomElement(['tum', '18_25', '26_35', '36_ustu']);
            $matchGenderFilter = fake()->randomElement(['tum', 'erkek', 'kadin']);

            return [
                'ad' => $firstName,
                'soyad' => $lastName,
                'kullanici_adi' => $username,
                'email' => fake()->unique()->safeEmail(),
                'password' => static::$password ??= Hash::make('password'),
                'google_kimlik' => 'ai-google-' . Str::uuid(),
                'apple_kimlik' => 'ai-apple-' . Str::uuid(),
                'hesap_tipi' => 'ai',
                'hesap_durumu' => 'aktif',
                'dogum_yili' => fake()->numberBetween(1990, 2003),
                'cinsiyet' => 'kadin',
                'ulke' => 'Türkiye',
                'il' => fake('tr_TR')->city(),
                'ilce' => fake('tr_TR')->citySuffix(),
                'biyografi' => fake('tr_TR')->sentence(12),
                'son_gorulme_tarihi' => now()->subMinutes(fake()->numberBetween(1, 30)),
                'cevrim_ici_mi' => true,
                'yaziyor_mu' => fake()->boolean(25),
                'ses_acik_mi' => fake()->boolean(80),
                'gorunum_modu' => fake()->randomElement(['acik', 'koyu', 'sistem']),
                'bildirimler_acik_mi' => true,
                'titresim_acik_mi' => true,
                'dil' => $language,
                'cihaz_bilgi' => json_encode([
                    'platform' => fake()->randomElement(['android', 'ios']),
                    'device' => fake()->randomElement(['iPhone 15', 'Galaxy S24', 'Pixel 9']),
                    'seeded' => true,
                ], JSON_UNESCAPED_UNICODE),
                'premium_aktif_mi' => fake()->boolean(35),
                'premium_bitis_tarihi' => now()->addDays(fake()->numberBetween(3, 60)),
                'profil_one_cikarma_aktif_mi' => fake()->boolean(20),
                'mevcut_puan' => fake()->numberBetween(120, 1200),
                'gunluk_ucretsiz_hak' => fake()->numberBetween(0, 3),
                'son_hak_yenileme_tarihi' => now()->subHours(fake()->numberBetween(1, 12)),
                'son_gunluk_giris_puani_tarihi' => now()->subDay(),
                'eslesme_cinsiyet_filtresi' => $matchGenderFilter,
                'eslesme_yas_filtresi' => $matchAgeFilter,
                'super_eslesme_aktif_mi' => fake()->boolean(40),
                'remember_token' => Str::random(10),
            ];
        });
    }

    public function premium(): static
    {
        return $this->state(fn() => [
            'premium_aktif_mi' => true,
            'premium_bitis_tarihi' => now()->addMonth(),
            'mevcut_puan' => fake()->numberBetween(500, 5000),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
