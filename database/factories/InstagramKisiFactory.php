<?php

namespace Database\Factories;

use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstagramKisi>
 */
class InstagramKisiFactory extends Factory
{
    protected $model = InstagramKisi::class;

    public function definition(): array
    {
        return [
            'instagram_hesap_id' => InstagramHesap::factory(),
            'instagram_kisi_id' => 'chat-' . fake()->unique()->numerify('######'),
            'kullanici_adi' => fake()->userName(),
            'gorunen_ad' => fake()->name(),
            'profil_resmi' => fake()->imageUrl(),
            'notlar' => fake()->optional()->sentence(),
            'son_mesaj_tarihi' => now(),
        ];
    }
}
