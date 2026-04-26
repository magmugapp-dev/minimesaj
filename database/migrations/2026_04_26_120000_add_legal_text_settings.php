<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            [
                'anahtar' => 'gizlilik_politikasi',
                'deger' => "Magmug gizlilik politikasini buradan duzenleyin.\n\nKullanici verilerinin hangi amaclarla islendigini, saklama surelerini ve iletisim kanallarini aciklayin.",
                'grup' => 'yasal',
                'tip' => 'text',
                'aciklama' => 'Mobil uygulamada gosterilen gizlilik politikasi',
            ],
            [
                'anahtar' => 'kvkk_aydinlatma_metni',
                'deger' => "Magmug KVKK aydinlatma metnini buradan duzenleyin.\n\nVeri sorumlusu, isleme amaclari, hukuki sebepler ve kullanici haklarini aciklayin.",
                'grup' => 'yasal',
                'tip' => 'text',
                'aciklama' => 'Mobil uygulamada gosterilen KVKK aydinlatma metni',
            ],
            [
                'anahtar' => 'kullanim_kosullari',
                'deger' => "Magmug kullanim kosullarini buradan duzenleyin.\n\nHesap kullanimi, yas kurallari, odeme kosullari ve kabul edilebilir kullanim ilkelerini aciklayin.",
                'grup' => 'yasal',
                'tip' => 'text',
                'aciklama' => 'Mobil uygulamada gosterilen kullanim kosullari',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('ayarlar')->updateOrInsert(
                ['anahtar' => $setting['anahtar']],
                $setting + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('ayarlar')
            ->whereIn('anahtar', [
                'gizlilik_politikasi',
                'kvkk_aydinlatma_metni',
                'kullanim_kosullari',
            ])
            ->delete();
    }
};
