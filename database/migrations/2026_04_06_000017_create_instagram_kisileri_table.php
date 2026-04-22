<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('instagram_kisileri', function (Blueprint $table) {
    $table->id();
    $table->foreignId('instagram_hesap_id')->constrained('instagram_hesaplari')->cascadeOnDelete();
    $table->string('instagram_kisi_id');
    $table->string('kullanici_adi')->nullable();
    $table->string('gorunen_ad')->nullable();
    $table->string('profil_resmi')->nullable();
    $table->text('notlar')->nullable();
    $table->timestamp('son_mesaj_tarihi')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_kisileri');
    }
};
