<?php

namespace Database\Seeders;

use App\Models\PuanPaketi;
use Illuminate\Database\Seeder;

class PuanPaketiSeeder extends Seeder
{
    public function run(): void
    {
        $paketler = [
            [
                'kod' => 'kredi_25',
                'android_urun_kodu' => 'kredi_25',
                'ios_urun_kodu' => 'kredi_25',
                'puan' => 25,
                'fiyat' => 49.99,
                'para_birimi' => 'TRY',
                'rozet' => null,
                'onerilen_mi' => false,
                'aktif' => true,
                'sira' => 10,
            ],
            [
                'kod' => 'kredi_60',
                'android_urun_kodu' => 'kredi_60',
                'ios_urun_kodu' => 'kredi_60',
                'puan' => 60,
                'fiyat' => 99.99,
                'para_birimi' => 'TRY',
                'rozet' => 'EN POPULER',
                'onerilen_mi' => true,
                'aktif' => true,
                'sira' => 20,
            ],
            [
                'kod' => 'kredi_150',
                'android_urun_kodu' => 'kredi_150',
                'ios_urun_kodu' => 'kredi_150',
                'puan' => 150,
                'fiyat' => 199.99,
                'para_birimi' => 'TRY',
                'rozet' => null,
                'onerilen_mi' => false,
                'aktif' => true,
                'sira' => 30,
            ],
        ];

        foreach ($paketler as $paket) {
            PuanPaketi::query()->updateOrCreate(
                ['kod' => $paket['kod']],
                $paket,
            );
        }
    }
}
