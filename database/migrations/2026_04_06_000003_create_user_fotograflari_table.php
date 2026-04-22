<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

Schema::create('user_fotograflari', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('dosya_yolu');
    $table->integer('sira_no')->default(0);
    $table->boolean('ana_fotograf_mi')->default(false);
    $table->boolean('aktif_mi')->default(true);
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fotograflari');
    }
};
