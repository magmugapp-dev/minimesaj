<?php

use App\Models\Ayar;

it('returns public app settings including version, support and store fields', function () {
    $ayarlar = [
        'site_adi' => ['deger' => 'Magmug', 'grup' => 'genel', 'tip' => 'string'],
        'uygulama_versiyonu' => ['deger' => '2.4.1', 'grup' => 'genel', 'tip' => 'string'],
        'mobil_minimum_versiyon' => ['deger' => '2.4.0', 'grup' => 'genel', 'tip' => 'string'],
        'varsayilan_dil' => ['deger' => 'tr', 'grup' => 'genel', 'tip' => 'string'],
        'kayit_aktif_mi' => ['deger' => '1', 'grup' => 'genel', 'tip' => 'boolean'],
        'destek_eposta' => ['deger' => 'destek@magmug.app', 'grup' => 'genel', 'tip' => 'string'],
        'destek_whatsapp' => ['deger' => '+905551112233', 'grup' => 'genel', 'tip' => 'string'],
        'android_play_store_url' => [
            'deger' => 'https://play.google.com/store/apps/details?id=com.magmug.magmug',
            'grup' => 'google_play',
            'tip' => 'string',
        ],
        'ios_app_store_url' => [
            'deger' => 'https://apps.apple.com/tr/app/magmug/id1234567890',
            'grup' => 'apple',
            'tip' => 'string',
        ],
    ];

    foreach ($ayarlar as $anahtar => $alanlar) {
        Ayar::query()->updateOrCreate(['anahtar' => $anahtar], $alanlar);
    }

    $this->getJson('/api/uygulama/ayarlar')
        ->assertOk()
        ->assertJsonPath('durum', true)
        ->assertJsonPath('veri.uygulama_adi', 'Magmug')
        ->assertJsonPath('veri.uygulama_versiyonu', '2.4.1')
        ->assertJsonPath('veri.mobil_minimum_versiyon', '2.4.0')
        ->assertJsonPath('veri.varsayilan_dil', 'tr')
        ->assertJsonPath('veri.kayit_aktif_mi', true)
        ->assertJsonPath('veri.destek_eposta', 'destek@magmug.app')
        ->assertJsonPath('veri.destek_whatsapp', '+905551112233')
        ->assertJsonPath('veri.android_play_store_url', 'https://play.google.com/store/apps/details?id=com.magmug.magmug')
        ->assertJsonPath('veri.ios_app_store_url', 'https://apps.apple.com/tr/app/magmug/id1234567890');
});
