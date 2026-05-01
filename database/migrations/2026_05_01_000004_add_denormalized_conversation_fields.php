<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sohbetler', function (Blueprint $table): void {
            $table->foreignId('son_mesaj_gonderen_user_id')->nullable()->after('son_mesaj_id')->constrained('users')->nullOnDelete();
            $table->string('son_mesaj_tipi', 32)->nullable()->after('son_mesaj_tarihi');
            $table->text('son_mesaj_metni')->nullable()->after('son_mesaj_tipi');
            $table->boolean('son_mesaj_okundu_mu')->default(false)->after('son_mesaj_metni');
            $table->unsignedInteger('user_okunmamis_sayisi')->default(0)->after('toplam_mesaj_sayisi');
            $table->unsignedInteger('eslesen_okunmamis_sayisi')->default(0)->after('user_okunmamis_sayisi');

            $table->index(['durum', 'son_mesaj_tarihi'], 'idx_sohbetler_durum_son_mesaj');
        });

        Schema::table('mesajlar', function (Blueprint $table): void {
            $table->index(['sohbet_id', 'gonderen_user_id', 'okundu_mu'], 'idx_mesajlar_sohbet_okundu');
            $table->index(['sohbet_id', 'id'], 'idx_mesajlar_sohbet_id');
        });

        Schema::table('eslesmeler', function (Blueprint $table): void {
            $table->index(['eslesen_user_id', 'durum'], 'idx_eslesmeler_eslesen_durum');
        });

        DB::table('sohbetler')
            ->whereNotNull('son_mesaj_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $message = DB::table('mesajlar')->where('id', $row->son_mesaj_id)->first();
                    $match = DB::table('eslesmeler')->where('id', $row->eslesme_id)->first();
                    if (!$message || !$match) {
                        continue;
                    }

                    DB::table('sohbetler')->where('id', $row->id)->update([
                        'son_mesaj_gonderen_user_id' => $message->gonderen_user_id,
                        'son_mesaj_tipi' => $message->mesaj_tipi,
                        'son_mesaj_metni' => $message->mesaj_metni ? mb_substr((string) $message->mesaj_metni, 0, 500) : null,
                        'son_mesaj_okundu_mu' => (bool) $message->okundu_mu,
                        'user_okunmamis_sayisi' => DB::table('mesajlar')
                            ->where('sohbet_id', $row->id)
                            ->where('gonderen_user_id', '!=', $match->user_id)
                            ->where('okundu_mu', false)
                            ->count(),
                        'eslesen_okunmamis_sayisi' => DB::table('mesajlar')
                            ->where('sohbet_id', $row->id)
                            ->where('gonderen_user_id', '!=', $match->eslesen_user_id)
                            ->where('okundu_mu', false)
                            ->count(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('eslesmeler', function (Blueprint $table): void {
            $table->dropIndex('idx_eslesmeler_eslesen_durum');
        });

        Schema::table('mesajlar', function (Blueprint $table): void {
            $table->dropIndex('idx_mesajlar_sohbet_okundu');
            $table->dropIndex('idx_mesajlar_sohbet_id');
        });

        Schema::table('sohbetler', function (Blueprint $table): void {
            $table->dropIndex('idx_sohbetler_durum_son_mesaj');
            $table->dropConstrainedForeignId('son_mesaj_gonderen_user_id');
            $table->dropColumn([
                'son_mesaj_tipi',
                'son_mesaj_metni',
                'son_mesaj_okundu_mu',
                'user_okunmamis_sayisi',
                'eslesen_okunmamis_sayisi',
            ]);
        });
    }
};
