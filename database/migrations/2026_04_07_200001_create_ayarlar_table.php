<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ayarlar', function (Blueprint $table) {
            $table->id();
            $table->string('anahtar')->unique();
            $table->text('deger')->nullable();
            $table->string('grup');
            $table->enum('tip', ['string', 'integer', 'boolean', 'json', 'text'])->default('string');
            $table->string('aciklama')->nullable();
            $table->timestamps();

            $table->index('grup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayarlar');
    }
};
