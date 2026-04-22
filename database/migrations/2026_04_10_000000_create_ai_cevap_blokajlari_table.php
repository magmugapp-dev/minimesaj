<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_cevap_blokajlari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instagram_hesap_id');
            $table->unsignedBigInteger('instagram_kisi_id');
            $table->timestamp('blokaj_bitis');
            $table->unique(['instagram_hesap_id', 'instagram_kisi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cevap_blokajlari');
    }
};
