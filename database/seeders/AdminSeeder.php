<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@minimesaj.com'],
            [
                'ad' => 'Admin',
                'soyad' => 'MiniMesaj',
                'kullanici_adi' => 'admin',
                'password' => Hash::make('admin123'),
                'hesap_tipi' => 'user',
                'hesap_durumu' => 'aktif',
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
