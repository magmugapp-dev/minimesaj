<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $ayarlar = [
        [
            'anahtar' => 'depolama_fotograf_dizini',
            'deger' => 'fotograflar',
            'grup' => 'depolama',
            'tip' => 'string',
            'aciklama' => 'Fotograf Klasor Dizin Adi',
        ],
        [
            'anahtar' => 'depolama_video_dizini',
            'deger' => 'videolar',
            'grup' => 'depolama',
            'tip' => 'string',
            'aciklama' => 'Video Klasor Dizin Adi',
        ],
        [
            'anahtar' => 'izinli_fotograf_uzantilari',
            'deger' => 'jpg,jpeg,png,gif,webp,heic,heif,bmp,svg',
            'grup' => 'depolama',
            'tip' => 'string',
            'aciklama' => 'Izinli fotograf uzantilari (virgulle)',
        ],
        [
            'anahtar' => 'izinli_video_uzantilari',
            'deger' => 'mp4,mov,avi,webm,m4v,3gp,mkv,flv,wmv',
            'grup' => 'depolama',
            'tip' => 'string',
            'aciklama' => 'Izinli video uzantilari (virgulle)',
        ],
        [
            'anahtar' => 'max_video_boyut_mb',
            'deger' => '100',
            'grup' => 'depolama',
            'tip' => 'integer',
            'aciklama' => 'Maksimum Video Boyutu (MB)',
        ],
    ];

    public function up(): void
    {
        foreach ($this->ayarlar as $ayar) {
            DB::table('ayarlar')->updateOrInsert(
                ['anahtar' => $ayar['anahtar']],
                $ayar,
            );
        }
    }

    public function down(): void
    {
        DB::table('ayarlar')
            ->whereIn('anahtar', array_column($this->ayarlar, 'anahtar'))
            ->delete();
    }
};
