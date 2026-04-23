<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sohbetler', function (Blueprint $table) {
            $table->string('ai_durumu')->nullable()->after('son_mesaj_tarihi');
            $table->string('ai_durum_metni')->nullable()->after('ai_durumu');
            $table->timestamp('ai_planlanan_cevap_at')->nullable()->after('ai_durum_metni');
            $table->timestamp('ai_durum_guncellendi_at')->nullable()->after('ai_planlanan_cevap_at');
        });
    }

    public function down(): void
    {
        Schema::table('sohbetler', function (Blueprint $table) {
            $table->dropColumn([
                'ai_durumu',
                'ai_durum_metni',
                'ai_planlanan_cevap_at',
                'ai_durum_guncellendi_at',
            ]);
        });
    }
};
