<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('yapay_zeka_gorevleri', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sohbet_id')->constrained('sohbetler')->cascadeOnDelete();
    $table->foreignId('gelen_mesaj_id')->constrained('mesajlar')->cascadeOnDelete();
    $table->foreignId('ai_user_id')->constrained('users')->cascadeOnDelete();
    $table->string('durum')->default('bekliyor');
    $table->integer('deneme_sayisi')->default(0);
    $table->text('hata_mesaji')->nullable();
    $table->longText('cevap_metni')->nullable();
    $table->enum('saglayici_tipi', ['gemini', 'openai'])->default('gemini');
    $table->string('model_adi')->nullable();
    $table->integer('giris_token_sayisi')->nullable();
    $table->integer('cikis_token_sayisi')->nullable();
    $table->integer('yanit_suresi_ms')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('yapay_zeka_gorevleri');
    }
};
