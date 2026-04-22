<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sohbetler', function (Blueprint $table) {
            $table->timestamp('ai_sessiz_mod_bitis_at')->nullable()->after('son_mesaj_tarihi');
            $table->foreignId('ai_sessiz_mod_tetikleyen_mesaj_id')
                ->nullable()
                ->after('ai_sessiz_mod_bitis_at')
                ->constrained('mesajlar')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sohbetler', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_sessiz_mod_tetikleyen_mesaj_id');
            $table->dropColumn('ai_sessiz_mod_bitis_at');
        });
    }
};
