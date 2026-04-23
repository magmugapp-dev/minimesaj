<?php

namespace Database\Factories;

use App\Models\InstagramHesap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstagramHesap>
 */
class InstagramHesapFactory extends Factory
{
    protected $model = InstagramHesap::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->aiKullanici(),
            'instagram_kullanici_adi' => fake()->unique()->userName(),
            'instagram_profil_id' => 'ig-' . fake()->unique()->numerify('######'),
            'otomatik_cevap_aktif_mi' => true,
            'yarim_otomatik_mod_aktif_mi' => false,
            'aktif_mi' => true,
            'son_baglanti_tarihi' => now(),
        ];
    }
}
