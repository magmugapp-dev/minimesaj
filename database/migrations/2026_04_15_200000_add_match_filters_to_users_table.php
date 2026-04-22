<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('eslesme_cinsiyet_filtresi', ['tum', 'kadin', 'erkek'])
                ->default('tum')
                ->after('gunluk_ucretsiz_hak');
            $table->enum('eslesme_yas_filtresi', ['tum', '18_25', '26_35', '36_ustu'])
                ->default('tum')
                ->after('eslesme_cinsiyet_filtresi');
            $table->boolean('super_eslesme_aktif_mi')
                ->default(false)
                ->after('eslesme_yas_filtresi');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'eslesme_cinsiyet_filtresi',
                'eslesme_yas_filtresi',
                'super_eslesme_aktif_mi',
            ]);
        });
    }
};
