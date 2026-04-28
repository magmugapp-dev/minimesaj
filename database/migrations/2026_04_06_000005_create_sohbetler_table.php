<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('sohbetler', function (Blueprint $table) {
    $table->id();
    $table->foreignId('eslesme_id')->constrained('eslesmeler')->cascadeOnDelete();
    $table->unsignedBigInteger('son_mesaj_id')->nullable();
    $table->timestamp('son_mesaj_tarihi')->nullable();
    $table->string('ai_durumu')->nullable();
    $table->string('ai_durum_metni')->nullable();
    $table->timestamp('ai_planlanan_cevap_at')->nullable();
    $table->timestamp('ai_durum_guncellendi_at')->nullable();
    $table->integer('toplam_mesaj_sayisi')->default(0);
    $table->string('durum')->default('aktif');
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('sohbetler');
    }
};
