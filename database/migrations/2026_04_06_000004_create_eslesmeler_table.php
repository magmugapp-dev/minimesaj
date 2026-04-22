<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('eslesmeler', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('eslesen_user_id')->constrained('users')->cascadeOnDelete();
    $table->enum('eslesme_turu', ['rastgele', 'otomatik', 'premium', 'geri_donus'])->default('rastgele');
    $table->enum('eslesme_kaynagi', ['gercek_kullanici', 'yapay_zeka'])->default('gercek_kullanici');
    $table->enum('durum', ['bekliyor', 'aktif', 'bitti', 'iptal'])->default('aktif');
    $table->boolean('tekrar_eslesebilir_mi')->default(false);
    $table->foreignId('baslatan_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('bitis_sebebi')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'eslesen_user_id']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('eslesmeler');
    }
};
