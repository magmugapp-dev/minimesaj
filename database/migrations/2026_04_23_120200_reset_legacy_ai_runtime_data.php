<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $aiUserIds = DB::table('users')
            ->where('hesap_tipi', 'ai')
            ->pluck('id');

        if ($aiUserIds->isEmpty()) {
            return;
        }

        $aiEslesmeIds = collect();
        if (Schema::hasTable('eslesmeler')) {
            $aiEslesmeIds = DB::table('eslesmeler')
                ->whereIn('user_id', $aiUserIds)
                ->orWhereIn('eslesen_user_id', $aiUserIds)
                ->pluck('id');
        }

        if ($aiEslesmeIds->isNotEmpty()) {
            $aiSohbetIds = collect();
            if (Schema::hasTable('sohbetler')) {
                $aiSohbetIds = DB::table('sohbetler')
                    ->whereIn('eslesme_id', $aiEslesmeIds)
                    ->pluck('id');
            }

            if ($aiSohbetIds->isNotEmpty()) {
                if (Schema::hasTable('yapay_zeka_gorevleri')) {
                    DB::table('yapay_zeka_gorevleri')->whereIn('sohbet_id', $aiSohbetIds)->delete();
                }
                if (Schema::hasTable('mesajlar')) {
                    DB::table('mesajlar')->whereIn('sohbet_id', $aiSohbetIds)->delete();
                }
                if (Schema::hasTable('sohbetler')) {
                    DB::table('sohbetler')->whereIn('id', $aiSohbetIds)->delete();
                }
            }

            if (Schema::hasTable('eslesmeler')) {
                DB::table('eslesmeler')->whereIn('id', $aiEslesmeIds)->delete();
            }
        }

        $instagramHesapIds = collect();
        if (Schema::hasTable('instagram_hesaplari')) {
            $instagramHesapIds = DB::table('instagram_hesaplari')
                ->whereIn('user_id', $aiUserIds)
                ->pluck('id');
        }

        if ($instagramHesapIds->isNotEmpty()) {
            if (Schema::hasTable('instagram_ai_gorevleri')) {
                DB::table('instagram_ai_gorevleri')
                    ->whereIn('instagram_hesap_id', $instagramHesapIds)
                    ->delete();
            }

            if (Schema::hasTable('instagram_mesajlari')) {
                DB::table('instagram_mesajlari')
                    ->whereIn('instagram_hesap_id', $instagramHesapIds)
                    ->delete();
            }

            if (Schema::hasTable('ai_cevap_blokajlari')) {
                DB::table('ai_cevap_blokajlari')
                    ->whereIn('instagram_hesap_id', $instagramHesapIds)
                    ->delete();
            }
        }

        if (Schema::hasTable('ai_hafizalari')) {
            DB::table('ai_hafizalari')->whereIn('ai_user_id', $aiUserIds)->delete();
        }
        if (Schema::hasTable('ai_memories')) {
            DB::table('ai_memories')->whereIn('ai_user_id', $aiUserIds)->delete();
        }
        if (Schema::hasTable('ai_conversation_states')) {
            DB::table('ai_conversation_states')->whereIn('ai_user_id', $aiUserIds)->delete();
        }
        if (Schema::hasTable('ai_turn_logs')) {
            DB::table('ai_turn_logs')->whereIn('ai_user_id', $aiUserIds)->delete();
        }
    }

    public function down(): void
    {
        // Veri temizligi geri alinmaz.
    }
};
