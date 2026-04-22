<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ayarlar')->updateOrInsert(
            ['anahtar' => 'apple_odeme_aktif_mi'],
            [
                'deger' => '0',
                'grup' => 'apple',
                'tip' => 'boolean',
                'aciklama' => 'App Store odemeleri aktif mi',
            ],
        );

        DB::table('ayarlar')->updateOrInsert(
            ['anahtar' => 'google_play_odeme_aktif_mi'],
            [
                'deger' => '0',
                'grup' => 'google_play',
                'tip' => 'boolean',
                'aciklama' => 'Google Play odemeleri aktif mi',
            ],
        );
    }

    public function down(): void
    {
        DB::table('ayarlar')
            ->whereIn('anahtar', ['apple_odeme_aktif_mi', 'google_play_odeme_aktif_mi'])
            ->delete();
    }
};
