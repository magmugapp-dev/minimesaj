<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puan_paketleri', function (Blueprint $table) {
            $table->id();
            $table->string('kod')->unique();
            $table->string('android_urun_kodu')->nullable()->unique();
            $table->string('ios_urun_kodu')->nullable()->unique();
            $table->unsignedInteger('puan');
            $table->decimal('fiyat', 10, 2);
            $table->string('para_birimi', 3)->default('TRY');
            $table->string('rozet')->nullable();
            $table->boolean('onerilen_mi')->default(false);
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('sira')->default(0);
            $table->timestamps();

            $table->index(['aktif', 'sira']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puan_paketleri');
    }
};
