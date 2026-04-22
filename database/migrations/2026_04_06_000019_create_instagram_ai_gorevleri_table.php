<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('instagram_ai_gorevleri', function (Blueprint $table) {
    $table->id();
    $table->foreignId('instagram_mesaj_id')->constrained('instagram_mesajlari')->cascadeOnDelete();
    $table->foreignId('instagram_hesap_id')->constrained('instagram_hesaplari')->cascadeOnDelete();
    $table->foreignId('instagram_kisi_id')->constrained('instagram_kisileri')->cascadeOnDelete();
    $table->string('durum')->default('bekliyor');
    $table->integer('deneme_sayisi')->default(0);
    $table->text('hata_mesaji')->nullable();
    $table->longText('cevap_metni')->nullable();
    $table->enum('saglayici_tipi', ['gemini', 'openai'])->default('gemini');
    $table->string('model_adi')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_ai_gorevleri');
    }
};
