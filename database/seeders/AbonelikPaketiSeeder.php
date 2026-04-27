<?php

namespace Database\Seeders;

use App\Models\AbonelikPaketi;
use Illuminate\Database\Seeder;

class AbonelikPaketiSeeder extends Seeder
{
    public function run(): void
    {
        $paketler = [
            [
                'kod' => 'premium_1_ay',
                'android_urun_kodu' => 'premium_1_ay',
                'ios_urun_kodu' => 'premium_1_ay',
                'sure_ay' => 1,
                'fiyat' => 149.99,
                'para_birimi' => 'TRY',
                'rozet' => null,
                'onerilen_mi' => true,
                'aktif' => true,
                'sira' => 10,
            ],
            [
                'kod' => 'premium_12_ay',
                'android_urun_kodu' => 'premium_12_ay',
                'ios_urun_kodu' => 'premium_12_ay',
                'sure_ay' => 12,
                'fiyat' => 999.99,
                'para_birimi' => 'TRY',
                'rozet' => 'EN POPULER',
                'onerilen_mi' => false,
                'aktif' => true,
                'sira' => 20,
            ],
        ];

        foreach ($paketler as $paket) {
            AbonelikPaketi::query()->updateOrCreate(
                ['kod' => $paket['kod']],
                $paket,
            );
        }
    }
}
