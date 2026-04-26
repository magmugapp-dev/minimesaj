<?php

use App\Models\Ayar;
use Illuminate\Support\Facades\Cache;

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
        'admob_aktif_mi' => ['deger' => '1', 'grup' => 'admob', 'tip' => 'boolean'],
        'admob_test_modu' => ['deger' => '0', 'grup' => 'admob', 'tip' => 'boolean'],
        'admob_android_app_id' => ['deger' => 'ca-app-pub-1111111111111111~2222222222', 'grup' => 'admob', 'tip' => 'string'],
        'admob_ios_app_id' => ['deger' => 'ca-app-pub-3333333333333333~4444444444', 'grup' => 'admob', 'tip' => 'string'],
        'admob_android_rewarded_unit_id' => ['deger' => 'ca-app-pub-1111111111111111/5555555555', 'grup' => 'admob', 'tip' => 'string'],
        'admob_ios_rewarded_unit_id' => ['deger' => 'ca-app-pub-3333333333333333/6666666666', 'grup' => 'admob', 'tip' => 'string'],
        'admob_android_match_native_unit_id' => ['deger' => 'ca-app-pub-1111111111111111/7777777777', 'grup' => 'admob', 'tip' => 'string'],
        'admob_ios_match_native_unit_id' => ['deger' => 'ca-app-pub-3333333333333333/8888888888', 'grup' => 'admob', 'tip' => 'string'],
        'reklam_odulu' => ['deger' => '19', 'grup' => 'puan_sistemi', 'tip' => 'integer'],
        'reklam_gunluk_odul_limiti' => ['deger' => '7', 'grup' => 'puan_sistemi', 'tip' => 'integer'],
    ];

    foreach ($ayarlar as $anahtar => $alanlar) {
        Ayar::query()->updateOrCreate(['anahtar' => $anahtar], $alanlar);
        Cache::forget('ayar:' . $anahtar);
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
        ->assertJsonPath('veri.ios_app_store_url', 'https://apps.apple.com/tr/app/magmug/id1234567890')
        ->assertJsonPath('veri.reklamlar.aktif_mi', true)
        ->assertJsonPath('veri.reklamlar.test_modu', false)
        ->assertJsonPath('veri.reklamlar.odul_puani', 19)
        ->assertJsonPath('veri.reklamlar.gunluk_odul_limiti', 7)
        ->assertJsonPath('veri.reklamlar.android.app_id', 'ca-app-pub-1111111111111111~2222222222')
        ->assertJsonPath('veri.reklamlar.android.rewarded_unit_id', 'ca-app-pub-1111111111111111/5555555555')
        ->assertJsonPath('veri.reklamlar.android.match_native_unit_id', 'ca-app-pub-1111111111111111/7777777777')
        ->assertJsonPath('veri.reklamlar.ios.app_id', 'ca-app-pub-3333333333333333~4444444444')
        ->assertJsonPath('veri.reklamlar.ios.rewarded_unit_id', 'ca-app-pub-3333333333333333/6666666666')
        ->assertJsonPath('veri.reklamlar.ios.match_native_unit_id', 'ca-app-pub-3333333333333333/8888888888');
});
