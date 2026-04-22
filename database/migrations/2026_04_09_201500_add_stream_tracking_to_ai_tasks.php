<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yapay_zeka_gorevleri', function (Blueprint $table) {
            $table->timestamp('istek_baslatildi_at')->nullable();
            $table->timestamp('son_parca_at')->nullable();
            $table->timestamp('tamamlandi_at')->nullable();
        });

        Schema::table('instagram_ai_gorevleri', function (Blueprint $table) {
            $table->timestamp('istek_baslatildi_at')->nullable();
            $table->timestamp('son_parca_at')->nullable();
            $table->timestamp('tamamlandi_at')->nullable();
            $table->integer('yanit_suresi_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('yapay_zeka_gorevleri', function (Blueprint $table) {
            $table->dropColumn([
                'istek_baslatildi_at',
                'son_parca_at',
                'tamamlandi_at',
            ]);
        });

        Schema::table('instagram_ai_gorevleri', function (Blueprint $table) {
            $table->dropColumn([
                'istek_baslatildi_at',
                'son_parca_at',
                'tamamlandi_at',
                'yanit_suresi_ms',
            ]);
        });
    }
};
