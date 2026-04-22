<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_fotograflari', function (Blueprint $table): void {
            $table->string('medya_tipi', 20)->default('fotograf')->after('dosya_yolu');
            $table->string('mime_tipi', 120)->nullable()->after('medya_tipi');
            $table->unsignedInteger('sure_saniye')->nullable()->after('mime_tipi');
            $table->string('onizleme_yolu')->nullable()->after('sure_saniye');
        });
    }

    public function down(): void
    {
        Schema::table('user_fotograflari', function (Blueprint $table): void {
            $table->dropColumn([
                'medya_tipi',
                'mime_tipi',
                'sure_saniye',
                'onizleme_yolu',
            ]);
        });
    }
};
