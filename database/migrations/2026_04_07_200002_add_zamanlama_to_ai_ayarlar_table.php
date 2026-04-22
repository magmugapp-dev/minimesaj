<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_ayarlar', function (Blueprint $table) {
            $table->string('saat_dilimi')->default('Europe/Istanbul')->after('gunduz_aktif_mi');
            $table->string('uyku_baslangic', 5)->default('23:00')->after('saat_dilimi');
            $table->string('uyku_bitis', 5)->default('07:30')->after('uyku_baslangic');
            $table->string('hafta_sonu_uyku_baslangic', 5)->nullable()->after('uyku_bitis');
            $table->string('hafta_sonu_uyku_bitis', 5)->nullable()->after('hafta_sonu_uyku_baslangic');
            $table->unsignedTinyInteger('rastgele_gecikme_dakika')->default(15)->after('hafta_sonu_uyku_bitis');

            $table->dropColumn(['gece_aktif_mi', 'gunduz_aktif_mi']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_ayarlar', function (Blueprint $table) {
            $table->dropColumn([
                'saat_dilimi',
                'uyku_baslangic',
                'uyku_bitis',
                'hafta_sonu_uyku_baslangic',
                'hafta_sonu_uyku_bitis',
                'rastgele_gecikme_dakika',
            ]);

            $table->boolean('gece_aktif_mi')->default(true)->after('foto_gonderebilir_mi');
            $table->boolean('gunduz_aktif_mi')->default(true)->after('gece_aktif_mi');
        });
    }
};
