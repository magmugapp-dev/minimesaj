<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $ayarlar = [
        [
            'anahtar' => 'mobil_minimum_versiyon',
            'deger' => '1.0.0',
            'grup' => 'genel',
            'tip' => 'string',
            'aciklama' => 'Mobil Minimum Versiyon',
        ],
        [
            'anahtar' => 'destek_eposta',
            'deger' => '',
            'grup' => 'genel',
            'tip' => 'string',
            'aciklama' => 'Destek E-posta Adresi',
        ],
        [
            'anahtar' => 'destek_whatsapp',
            'deger' => '',
            'grup' => 'genel',
            'tip' => 'string',
            'aciklama' => 'Destek WhatsApp Numarası',
        ],
        [
            'anahtar' => 'ios_app_store_url',
            'deger' => '',
            'grup' => 'apple',
            'tip' => 'string',
            'aciklama' => 'App Store Uygulama Linki',
        ],
        [
            'anahtar' => 'android_play_store_url',
            'deger' => 'https://play.google.com/store/apps/details?id=com.magmug.magmug',
            'grup' => 'google_play',
            'tip' => 'string',
            'aciklama' => 'Play Store Uygulama Linki',
        ],
    ];

    private array $dosyaAlanlari = [
        'apple_private_key_path' => [
            'tip' => 'file',
            'aciklama' => 'Apple Private Key Dosyası',
        ],
        'google_play_service_account_path' => [
            'tip' => 'file',
            'aciklama' => 'Google Play Service Account JSON',
        ],
        'apns_sertifika_yolu' => [
            'tip' => 'file',
            'aciklama' => 'APNs Sertifika Dosyası',
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

        foreach ($this->dosyaAlanlari as $anahtar => $alan) {
            DB::table('ayarlar')
                ->where('anahtar', $anahtar)
                ->update($alan);
        }
    }

    public function down(): void
    {
        DB::table('ayarlar')
            ->whereIn('anahtar', array_column($this->ayarlar, 'anahtar'))
            ->delete();

        DB::table('ayarlar')
            ->where('anahtar', 'apple_private_key_path')
            ->update([
                'tip' => 'string',
                'aciklama' => 'Apple Private Key Dosya Yolu',
            ]);

        DB::table('ayarlar')
            ->where('anahtar', 'google_play_service_account_path')
            ->update([
                'tip' => 'string',
                'aciklama' => 'Google Play Service Account Dosya Yolu',
            ]);

        DB::table('ayarlar')
            ->where('anahtar', 'apns_sertifika_yolu')
            ->update([
                'tip' => 'string',
                'aciklama' => 'APNs Sertifika Yolu',
            ]);
    }
};
