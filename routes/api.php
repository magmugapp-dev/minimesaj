<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Tüm API route'ları /api prefix'i altında çalışır.
| Sanctum middleware ile korunan route'lar auth:sanctum kullanır.
|
*/

// ── Uygulama Ayarları (Public) ───────────────────────────────────────
Route::prefix('uygulama')->middleware('throttle:api')->group(function () {
    Route::get('ayarlar', [\App\Http\Controllers\Api\UygulamaAyarController::class, 'index']);
    Route::get('logo', [\App\Http\Controllers\Api\UygulamaAyarController::class, 'logo']);
});

if (app()->environment('testing')) {
    Route::get('hesaplar/{hesap}/giden-kuyruk', [\App\Http\Controllers\Instagram\MesajController::class, 'gidenKuyruk']);
}

// ── Kimlik Doğrulama (Public) ────────────────────────────────────────
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('kayit', [\App\Http\Controllers\Kimlik\KimlikController::class, 'kayit']);
    Route::post('giris', [\App\Http\Controllers\Kimlik\KimlikController::class, 'giris']);
    Route::post('sosyal/giris', [\App\Http\Controllers\Kimlik\SosyalKimlikController::class, 'giris']);
    Route::post('sosyal/kayit', [\App\Http\Controllers\Kimlik\SosyalKimlikController::class, 'kayit']);
    Route::get('kullanici-adi-musait', [\App\Http\Controllers\Kimlik\SosyalKimlikController::class, 'kullaniciAdiMusait']);
});

// ── Korumalı Route'lar ───────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    Route::post('broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });

    // Kimlik
    Route::post('auth/cikis', [\App\Http\Controllers\Kimlik\KimlikController::class, 'cikis']);
    Route::get('auth/ben', [\App\Http\Controllers\Kimlik\KimlikController::class, 'ben']);
    Route::delete('auth/hesap', [\App\Http\Controllers\Kimlik\KimlikController::class, 'hesapSil']);
    Route::post('uygulama/destek-talebi', [\App\Http\Controllers\Api\DestekTalebiController::class, 'gonder']);

    // ── Dating (Flutter) ─────────────────────────────────────────────
    Route::prefix('dating')->group(function () {
        Route::get('kesfet', [\App\Http\Controllers\Dating\KesfetController::class, 'index']);
        Route::get('eslesme-merkezi', [\App\Http\Controllers\Dating\EslesmeMerkeziController::class, 'merkez']);
        Route::patch('eslesme-tercihleri', [\App\Http\Controllers\Dating\EslesmeMerkeziController::class, 'tercihleriGuncelle']);
        Route::post('eslesme-baslat', [\App\Http\Controllers\Dating\EslesmeMerkeziController::class, 'baslat']);
        Route::post('eslesme-gec/{kullanici}', [\App\Http\Controllers\Dating\EslesmeMerkeziController::class, 'gec']);
        Route::post('eslesme-sohbet/{kullanici}', [\App\Http\Controllers\Dating\EslesmeMerkeziController::class, 'sohbetBaslat']);
        Route::get('profil', [\App\Http\Controllers\Dating\ProfilController::class, 'goster']);
        Route::get('profil/{kullanici}', [\App\Http\Controllers\Dating\ProfilController::class, 'kullanici']);
        Route::patch('profil', [\App\Http\Controllers\Dating\ProfilController::class, 'guncelle']);

        Route::apiResource('fotograflar', \App\Http\Controllers\Dating\FotografController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['fotograflar' => 'fotograf']);

        Route::get('eslesmeler', [\App\Http\Controllers\Dating\EslesmeController::class, 'listele']);
        Route::post('eslesmeler/{eslesme}/bitir', [\App\Http\Controllers\Dating\EslesmeController::class, 'bitir']);

        Route::get('sohbetler', [\App\Http\Controllers\Dating\SohbetController::class, 'listele']);
        Route::get('sohbetler/{sohbet}/mesajlar', [\App\Http\Controllers\Dating\MesajController::class, 'listele']);
        Route::post('sohbetler/{sohbet}/mesajlar', [\App\Http\Controllers\Dating\MesajController::class, 'gonder']);
        Route::post('sohbetler/{sohbet}/mesajlar/{mesaj}/ceviri', [\App\Http\Controllers\Dating\MesajController::class, 'cevir']);
        Route::patch('sohbetler/{sohbet}/oku', [\App\Http\Controllers\Dating\MesajController::class, 'okuduIsaretle']);
        Route::post('medya-yukle', [\App\Http\Controllers\Dating\MedyaController::class, 'yukle']);
        Route::post('sessize-al/{kullanici}', [\App\Http\Controllers\Dating\SessizeAlmaController::class, 'sessizeAl']);
        Route::delete('sessize-al/{kullanici}', [\App\Http\Controllers\Dating\SessizeAlmaController::class, 'kaldir']);

        Route::get('engeller', [\App\Http\Controllers\Moderasyon\EngellemeController::class, 'listele']);
        Route::post('engelle/{kullanici}', [\App\Http\Controllers\Moderasyon\EngellemeController::class, 'engelle']);
        Route::delete('engelle/{kullanici}', [\App\Http\Controllers\Moderasyon\EngellemeController::class, 'kaldir']);
        Route::post('sikayet', [\App\Http\Controllers\Moderasyon\SikayetController::class, 'olustur']);

        // Bildirimler
        Route::post('bildirim-cihazlari', [\App\Http\Controllers\Dating\BildirimController::class, 'cihazKaydet']);
        Route::delete('bildirim-cihazlari', [\App\Http\Controllers\Dating\BildirimController::class, 'cihazSil']);
        Route::patch('bildirim-ayarlari', [\App\Http\Controllers\Dating\BildirimController::class, 'ayarGuncelle']);
        Route::get('bildirimler', [\App\Http\Controllers\Dating\BildirimController::class, 'listele']);
        Route::get('bildirimler/okunmamis', [\App\Http\Controllers\Dating\BildirimController::class, 'okunmamisSayisi']);
        Route::patch('bildirimler/oku', [\App\Http\Controllers\Dating\BildirimController::class, 'okuduIsaretle']);
        Route::patch('bildirimler/{bildirim}/oku', [\App\Http\Controllers\Dating\BildirimController::class, 'tekOku']);
    });

    // ── Instagram (Chrome Eklentisi) ─────────────────────────────────
    Route::prefix('instagram')->group(function () {
        Route::get('hesaplar', [\App\Http\Controllers\Instagram\HesapController::class, 'listele']);
        Route::post('hesaplar', [\App\Http\Controllers\Instagram\HesapController::class, 'bagla']);
        Route::delete('hesaplar/{hesap}', [\App\Http\Controllers\Instagram\HesapController::class, 'kaldir']);

        Route::get('hesaplar/{hesap}/kisiler', [\App\Http\Controllers\Instagram\KisiController::class, 'listele']);
        Route::post('hesaplar/{hesap}/kisiler/senkronize', [\App\Http\Controllers\Instagram\KisiController::class, 'senkronize']);

        Route::post('hesaplar/{hesap}/mesajlar', [\App\Http\Controllers\Instagram\MesajController::class, 'alVeKaydet']);
        Route::get('hesaplar/{hesap}/giden-kuyruk', [\App\Http\Controllers\Instagram\MesajController::class, 'gidenKuyruk']);
        Route::patch('mesajlar/{mesaj}/gonderildi', [\App\Http\Controllers\Instagram\MesajController::class, 'gonderildiIsaretle']);
    });

    // ── Yapay Zeka ───────────────────────────────────────────────────
    Route::prefix('ai')->middleware('throttle:ai')->group(function () {
        Route::get('ayarlar', [\App\Http\Controllers\YapayZeka\AiAyarController::class, 'goster']);
        Route::put('ayarlar', [\App\Http\Controllers\YapayZeka\AiAyarController::class, 'guncelle']);
    });

    // ── Ödeme & Puan ─────────────────────────────────────────────────
    Route::prefix('odeme')->group(function () {
        Route::get('bakiye', [\App\Http\Controllers\Odeme\PuanController::class, 'bakiye']);
        Route::get('hareketler', [\App\Http\Controllers\Odeme\PuanController::class, 'hareketler']);
        Route::get('paketler', [\App\Http\Controllers\Odeme\PuanPaketiController::class, 'index']);
        Route::get('abonelik-paketler', [\App\Http\Controllers\Odeme\AbonelikPaketiController::class, 'index']);
        Route::post('dogrula', [\App\Http\Controllers\Odeme\OdemeController::class, 'dogrula']);
        Route::post('reklam-odul', [\App\Http\Controllers\Odeme\ReklamOdulController::class, 'kaydet']);
    });

    // ── Hediye ───────────────────────────────────────────────────────
    Route::get('hediyeler', [\App\Http\Controllers\Odeme\HediyeController::class, 'index']);
    Route::post('hediye/gonder', [\App\Http\Controllers\Odeme\HediyeController::class, 'gonder']);

    // ── Admin / İstatistik ───────────────────────────────────────────
    Route::prefix('admin')->middleware('yetenek:admin')->group(function () {
        Route::get('istatistik/ozet', [\App\Http\Controllers\Yonetim\IstatistikController::class, 'gunlukOzet']);
        Route::get('sikayetler', [\App\Http\Controllers\Yonetim\SikayetYonetimController::class, 'listele']);
        Route::patch('sikayetler/{sikayet}', [\App\Http\Controllers\Yonetim\SikayetYonetimController::class, 'guncelle']);
    });
});
