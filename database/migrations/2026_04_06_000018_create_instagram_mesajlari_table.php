<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('instagram_mesajlari', function (Blueprint $table) {
    $table->id();
    $table->foreignId('instagram_hesap_id')->constrained('instagram_hesaplari')->cascadeOnDelete();
    $table->foreignId('instagram_kisi_id')->constrained('instagram_kisileri')->cascadeOnDelete();
    $table->enum('gonderen_tipi', ['biz', 'karsi_taraf', 'ai']);
    $table->longText('mesaj_metni')->nullable();
    $table->enum('mesaj_tipi', ['metin', 'ses', 'foto'])->default('metin');
    $table->boolean('ai_cevapladi_mi')->default(false);
    $table->boolean('gonderildi_mi')->default(false);
    $table->string('instagram_mesaj_kodu')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_mesajlari');
    }
};
