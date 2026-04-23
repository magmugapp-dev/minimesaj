<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $ayarlar = [
        [
            'anahtar' => 'normal_eslesme_kadin_cikma_orani',
            'deger' => '34',
            'grup' => 'puan_sistemi',
            'tip' => 'integer',
            'aciklama' => 'Normal Eslesme Kadin Cikma Orani (%)',
        ],
        [
            'anahtar' => 'normal_eslesme_erkek_cikma_orani',
            'deger' => '66',
            'grup' => 'puan_sistemi',
            'tip' => 'integer',
            'aciklama' => 'Normal Eslesme Erkek Cikma Orani (%)',
        ],
        [
            'anahtar' => 'normal_eslesme_kadin_maliyeti',
            'deger' => '8',
            'grup' => 'puan_sistemi',
            'tip' => 'integer',
            'aciklama' => 'Normal Eslesme Kadin Filtresi Maliyeti',
        ],
        [
            'anahtar' => 'normal_eslesme_erkek_maliyeti',
            'deger' => '8',
            'grup' => 'puan_sistemi',
            'tip' => 'integer',
            'aciklama' => 'Normal Eslesme Erkek Filtresi Maliyeti',
        ],
        [
            'anahtar' => 'super_eslesme_kadin_cikma_orani',
            'deger' => '51',
            'grup' => 'puan_sistemi',
            'tip' => 'integer',
            'aciklama' => 'Super Eslesme Kadin Cikma Orani (%)',
        ],
        [
            'anahtar' => 'super_eslesme_erkek_cikma_orani',
            'deger' => '49',
            'grup' => 'puan_sistemi',
            'tip' => 'integer',
            'aciklama' => 'Super Eslesme Erkek Cikma Orani (%)',
        ],
        [
            'anahtar' => 'super_eslesme_kadin_maliyeti',
            'deger' => '8',
            'grup' => 'puan_sistemi',
            'tip' => 'integer',
            'aciklama' => 'Super Eslesme Kadin Filtresi Maliyeti',
        ],
        [
            'anahtar' => 'super_eslesme_erkek_maliyeti',
            'deger' => '8',
            'grup' => 'puan_sistemi',
            'tip' => 'integer',
            'aciklama' => 'Super Eslesme Erkek Filtresi Maliyeti',
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
