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
        return $this->state(fn() => [
            'hesap_tipi' => 'ai',
            'ad' => fake('tr_TR')->firstName('female'),
            'cinsiyet' => 'kadin',
            'biyografi' => fake('tr_TR')->sentence(12),
            'cevrim_ici_mi' => true,
        ]);
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
