<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessize_alinan_kullanicilar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sessize_alinan_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sessiz_bitis_tarihi')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sessize_alinan_user_id'], 'sessize_alinan_unique');
            $table->index('sessiz_bitis_tarihi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessize_alinan_kullanicilar');
    }
};
