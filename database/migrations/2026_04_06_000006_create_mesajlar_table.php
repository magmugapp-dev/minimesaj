<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('mesajlar', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sohbet_id')->constrained('sohbetler')->cascadeOnDelete();
    $table->foreignId('gonderen_user_id')->constrained('users')->cascadeOnDelete();
    $table->enum('mesaj_tipi', ['metin', 'ses', 'foto', 'sistem'])->default('metin');
    $table->longText('mesaj_metni')->nullable();
    $table->string('dil_kodu', 12)->nullable();
    $table->string('dil_adi', 80)->nullable();
    $table->json('ceviriler')->nullable();
    $table->string('dosya_yolu')->nullable();
    $table->integer('dosya_suresi')->nullable();
    $table->bigInteger('dosya_boyutu')->nullable();
    $table->boolean('okundu_mu')->default(false);
    $table->boolean('silindi_mi')->default(false);
    $table->boolean('herkesten_silindi_mi')->default(false);
    $table->boolean('ai_tarafindan_uretildi_mi')->default(false);
    $table->unsignedBigInteger('cevaplanan_mesaj_id')->nullable();
    $table->timestamps();
    $table->index(['sohbet_id', 'created_at']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('mesajlar');
    }
};
