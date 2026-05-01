<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sohbetler', function (Blueprint $table): void {
            $table->timestamp('ai_konusma_kapanisi_at')->nullable()->after('ai_durum_guncellendi_at');
            $table->string('ai_kapanis_kategorisi', 20)->nullable()->after('ai_konusma_kapanisi_at');
            $table->timestamp('ai_ghost_lockout_until')->nullable()->after('ai_kapanis_kategorisi');
            $table->string('ai_ghost_tipi', 20)->nullable()->after('ai_ghost_lockout_until');
            $table->timestamp('temizlendi_at')->nullable()->after('ai_ghost_tipi');
        });
    }

    public function down(): void
    {
        Schema::table('sohbetler', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_konusma_kapanisi_at',
                'ai_kapanis_kategorisi',
                'ai_ghost_lockout_until',
                'ai_ghost_tipi',
                'temizlendi_at',
            ]);
        });
    }
};
