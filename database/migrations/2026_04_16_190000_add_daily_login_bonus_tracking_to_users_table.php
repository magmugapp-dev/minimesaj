<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('son_gunluk_giris_puani_tarihi')
                ->nullable()
                ->after('son_hak_yenileme_tarihi');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('son_gunluk_giris_puani_tarihi');
        });
    }
};
