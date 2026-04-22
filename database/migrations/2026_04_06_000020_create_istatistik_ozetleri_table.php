<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('istatistik_ozetleri', function (Blueprint $table) {
    $table->id();
    $table->date('tarih')->unique();
    $table->integer('toplam_eslesme_sayisi')->default(0);
    $table->integer('gercek_kullanici_eslesme_sayisi')->default(0);
    $table->integer('yapay_zeka_eslesme_sayisi')->default(0);
    $table->integer('ortalama_sohbet_suresi_saniye')->default(0);
    $table->decimal('reklam_izleme_orani', 5, 2)->default(0);
    $table->decimal('kullanici_tutma_orani', 5, 2)->default(0);
    $table->integer('engelleme_sayisi')->default(0);
    $table->integer('sikayet_sayisi')->default(0);
    $table->foreignId('en_cok_konusan_ai_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('istatistik_ozetleri');
    }
};
