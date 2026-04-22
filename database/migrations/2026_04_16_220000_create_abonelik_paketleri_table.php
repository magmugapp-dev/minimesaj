<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonelik_paketleri', function (Blueprint $table): void {
            $table->id();
            $table->string('kod')->unique();
            $table->string('android_urun_kodu')->nullable()->unique();
            $table->string('ios_urun_kodu')->nullable()->unique();
            $table->unsignedSmallInteger('sure_ay');
            $table->decimal('fiyat', 10, 2);
            $table->string('para_birimi', 3)->default('TRY');
            $table->string('rozet')->nullable();
            $table->boolean('onerilen_mi')->default(false);
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('sira')->default(10);
            $table->timestamps();
        });

        if (!DB::table('abonelik_paketleri')->exists()) {
            DB::table('abonelik_paketleri')->insert([
                [
                    'kod' => 'premium_1_ay',
                    'android_urun_kodu' => 'premium_1_ay',
                    'ios_urun_kodu' => 'premium_1_ay',
                    'sure_ay' => 1,
                    'fiyat' => 3.99,
                    'para_birimi' => 'USD',
                    'rozet' => null,
                    'onerilen_mi' => true,
                    'aktif' => true,
                    'sira' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'kod' => 'premium_12_ay',
                    'android_urun_kodu' => 'premium_12_ay',
                    'ios_urun_kodu' => 'premium_12_ay',
                    'sure_ay' => 12,
                    'fiyat' => 23.88,
                    'para_birimi' => 'USD',
                    'rozet' => 'EN POPULER',
                    'onerilen_mi' => false,
                    'aktif' => true,
                    'sira' => 20,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('abonelik_paketleri');
    }
};
