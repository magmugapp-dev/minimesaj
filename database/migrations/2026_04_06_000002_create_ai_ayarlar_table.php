<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('ai_ayarlar', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
    $table->boolean('aktif_mi')->default(true);
    $table->enum('saglayici_tipi', ['gemini', 'openai'])->default('gemini');
    $table->string('model_adi')->nullable();
    $table->enum('yedek_saglayici_tipi', ['gemini', 'openai'])->nullable();
    $table->string('yedek_model_adi')->nullable();
    $table->string('kisilik_tipi')->nullable();
    $table->text('kisilik_aciklamasi')->nullable();
    $table->string('konusma_tonu')->nullable();
    $table->string('konusma_stili')->nullable();
    $table->unsignedTinyInteger('emoji_seviyesi')->default(5);
    $table->unsignedTinyInteger('flort_seviyesi')->default(5);
    $table->unsignedTinyInteger('giriskenlik_seviyesi')->default(5);
    $table->unsignedTinyInteger('utangaclik_seviyesi')->default(5);
    $table->unsignedTinyInteger('duygusallik_seviyesi')->default(5);
    $table->unsignedTinyInteger('kiskanclik_seviyesi')->default(5);
    $table->unsignedTinyInteger('mizah_seviyesi')->default(5);
    $table->unsignedTinyInteger('zeka_seviyesi')->default(5);
    $table->boolean('ilk_mesaj_atar_mi')->default(false);
    $table->text('ilk_mesaj_sablonu')->nullable();
    $table->integer('gunluk_konusma_limiti')->default(100);
    $table->integer('tek_kullanici_gunluk_mesaj_limiti')->default(30);
    $table->integer('minimum_cevap_suresi_saniye')->default(5);
    $table->integer('maksimum_cevap_suresi_saniye')->default(40);
    $table->integer('ortalama_mesaj_uzunlugu')->nullable();
    $table->integer('mesaj_uzunlugu_min')->nullable();
    $table->integer('mesaj_uzunlugu_max')->nullable();
    $table->boolean('sesli_mesaj_gonderebilir_mi')->default(false);
    $table->boolean('foto_gonderebilir_mi')->default(false);
    $table->boolean('gece_aktif_mi')->default(true);
    $table->boolean('gunduz_aktif_mi')->default(true);
    $table->longText('sistem_komutu')->nullable();
    $table->json('yasakli_konular')->nullable();
    $table->json('zorunlu_kurallar')->nullable();
    $table->boolean('hafiza_aktif_mi')->default(true);
    $table->string('hafiza_seviyesi')->nullable();
    $table->boolean('kullaniciyi_hatirlar_mi')->default(true);
    $table->boolean('iliski_seviyesi_takibi_aktif_mi')->default(true);
    $table->string('puanlama_etiketi')->nullable();
    $table->decimal('temperature', 3, 2)->nullable();
    $table->decimal('top_p', 3, 2)->nullable();
    $table->integer('max_output_tokens')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_ayarlar');
    }
};
