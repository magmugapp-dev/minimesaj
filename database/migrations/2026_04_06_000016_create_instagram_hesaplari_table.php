<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('instagram_hesaplari', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('instagram_kullanici_adi');
    $table->string('instagram_profil_id')->nullable();
    $table->boolean('otomatik_cevap_aktif_mi')->default(true);
    $table->boolean('yarim_otomatik_mod_aktif_mi')->default(false);
    $table->timestamp('son_baglanti_tarihi')->nullable();
    $table->boolean('aktif_mi')->default(true);
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_hesaplari');
    }
};
