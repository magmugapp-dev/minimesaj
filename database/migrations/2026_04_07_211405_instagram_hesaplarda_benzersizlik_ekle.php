<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('instagram_hesaplari', function (Blueprint $table) {
            // Eğer yoksa kullanici_id alanını ekle
            if (!Schema::hasColumn('instagram_hesaplari', 'kullanici_id')) {
                $table->unsignedBigInteger('kullanici_id')->nullable();
            }
            $table->unique(['kullanici_id', 'instagram_kullanici_adi'], 'ig_kullanici_hesap_benzersiz');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instagram_hesaplari', function (Blueprint $table) {
            $table->dropUnique('ig_kullanici_hesap_benzersiz');
        });
    }
};
