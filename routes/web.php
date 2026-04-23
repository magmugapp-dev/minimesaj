<?php

use App\Http\Controllers\Admin\AiController;
use App\Http\Controllers\Admin\AbonelikPaketiController;
use App\Http\Controllers\Admin\AyarController;
use App\Http\Controllers\Admin\EngelController;
use App\Http\Controllers\Admin\EslesmeController;
use App\Http\Controllers\Admin\FinansalController;
use App\Http\Controllers\Admin\GirisController;
use App\Http\Controllers\Admin\HediyeController;
use App\Http\Controllers\Admin\InfluencerController;
use App\Http\Controllers\Admin\InstagramController;
use App\Http\Controllers\Admin\IstatistikController;
use App\Http\Controllers\Admin\KullaniciController;
use App\Http\Controllers\Admin\PanoController;
use App\Http\Controllers\Admin\DestekTalebiController;
use App\Http\Controllers\Admin\PuanPaketiController;
use App\Http\Controllers\Admin\SikayetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ── Admin Panel ──────────────────────────────────────────────────────
Route::prefix('admin')->group(function () {
    // Giriş (guest)
    Route::get('giris', [GirisController::class, 'form'])->name('admin.giris');
    Route::post('giris', [GirisController::class, 'giris']);

    // Korumalı rotalar
    Route::middleware('admin')->group(function () {
        Route::post('cikis', [GirisController::class, 'cikis'])->name('admin.cikis');
        Route::get('/', [PanoController::class, 'index'])->name('admin.pano');

        // Kullanıcı Yönetimi
        Route::get('kullanicilar', [KullaniciController::class, 'index'])->name('admin.kullanicilar.index');
        Route::get('kullanicilar/{kullanici}', [KullaniciController::class, 'goster'])->name('admin.kullanicilar.goster');
        Route::get('kullanicilar/{kullanici}/duzenle', [KullaniciController::class, 'duzenle'])->name('admin.kullanicilar.duzenle');
        Route::put('kullanicilar/{kullanici}', [KullaniciController::class, 'guncelle'])->name('admin.kullanicilar.guncelle');
        Route::patch('kullanicilar/{kullanici}/durum', [KullaniciController::class, 'durumGuncelle'])->name('admin.kullanicilar.durum');

        // AI Yönetimi
        Route::get('ai', [AiController::class, 'index'])->name('admin.ai.index');
        Route::get('ai/ekle', [AiController::class, 'ekle'])->name('admin.ai.ekle');
        Route::post('ai/ekle', [AiController::class, 'kaydet'])->name('admin.ai.kaydet');
        Route::get('ai/json-ekle', [AiController::class, 'jsonEkle'])->name('admin.ai.json-ekle');
        Route::post('ai/json-ekle', [AiController::class, 'jsonKaydet'])->name('admin.ai.json-kaydet');
        Route::post('ai/toplu-durum', [AiController::class, 'topluDurumGuncelle'])->name('admin.ai.toplu-durum');
        Route::get('ai/{kullanici}', [AiController::class, 'goster'])->name('admin.ai.goster');
        Route::get('ai/{kullanici}/duzenle', [AiController::class, 'duzenle'])->name('admin.ai.duzenle');
        Route::put('ai/{kullanici}', [AiController::class, 'guncelle'])->name('admin.ai.guncelle');

        // Moderasyon — Şikayetler
        Route::get('moderasyon/sikayetler', [SikayetController::class, 'index'])->name('admin.moderasyon.sikayetler');
        Route::post('moderasyon/sikayetler/toplu-durum', [SikayetController::class, 'topluDurumGuncelle'])->name('admin.moderasyon.sikayetler.toplu-durum');
        Route::get('moderasyon/sikayetler/{sikayet}', [SikayetController::class, 'goster'])->name('admin.moderasyon.sikayetler.goster');
        Route::patch('moderasyon/sikayetler/{sikayet}/durum', [SikayetController::class, 'durumGuncelle'])->name('admin.moderasyon.sikayetler.durum-guncelle');

        // Moderasyon — Engellemeler
        Route::get('moderasyon/engeller', [EngelController::class, 'index'])->name('admin.moderasyon.engeller');
        Route::delete('moderasyon/engeller/{engelleme}', [EngelController::class, 'kaldir'])->name('admin.moderasyon.engeller.kaldir');

        // Moderasyon — Destek Talepleri
        Route::get('moderasyon/destek-talepleri', [DestekTalebiController::class, 'index'])->name('admin.moderasyon.destek-talepleri');
        Route::get('moderasyon/destek-talepleri/{destekTalebi}', [DestekTalebiController::class, 'goster'])->name('admin.moderasyon.destek-talepleri.goster');
        Route::patch('moderasyon/destek-talepleri/{destekTalebi}/durum', [DestekTalebiController::class, 'durumGuncelle'])->name('admin.moderasyon.destek-talepleri.durum-guncelle');
        Route::post('moderasyon/destek-talepleri/{destekTalebi}/yanitlar', [DestekTalebiController::class, 'yanitEkle'])->name('admin.moderasyon.destek-talepleri.yanit-ekle');

        // Eşleşme Yönetimi
        Route::get('eslesmeler', [EslesmeController::class, 'index'])->name('admin.eslesmeler.index');
        Route::get('eslesmeler/{eslesme}/kullanicilar/{kullanici}/hafiza', [EslesmeController::class, 'kisiHafiza'])->name('admin.eslesmeler.kisi-hafiza');
        Route::get('eslesmeler/{eslesme}/sohbet', [EslesmeController::class, 'sohbet'])->name('admin.eslesmeler.sohbet');
        Route::get('eslesmeler/{eslesme}', [EslesmeController::class, 'goster'])->name('admin.eslesmeler.goster');
        Route::patch('eslesmeler/{eslesme}/durum', [EslesmeController::class, 'durumGuncelle'])->name('admin.eslesmeler.durum-guncelle');

        // Finansal
        Route::get('finansal/odemeler', [FinansalController::class, 'odemeler'])->name('admin.finansal.odemeler');
        Route::get('finansal/odemeler/{odeme}', [FinansalController::class, 'odemeGoster'])->name('admin.finansal.odeme-detay');
        Route::get('finansal/puan-hareketleri', [FinansalController::class, 'puanHareketleri'])->name('admin.finansal.puan-hareketleri');
        Route::get('finansal/aboneler', [FinansalController::class, 'aboneler'])->name('admin.finansal.aboneler');
        Route::get('finansal/puan-paketleri', [PuanPaketiController::class, 'index'])->name('admin.finansal.puan-paketleri.index');
        Route::get('finansal/puan-paketleri/ekle', [PuanPaketiController::class, 'create'])->name('admin.finansal.puan-paketleri.create');
        Route::post('finansal/puan-paketleri', [PuanPaketiController::class, 'store'])->name('admin.finansal.puan-paketleri.store');
        Route::get('finansal/puan-paketleri/{puanPaketi}/duzenle', [PuanPaketiController::class, 'edit'])->name('admin.finansal.puan-paketleri.edit');
        Route::put('finansal/puan-paketleri/{puanPaketi}', [PuanPaketiController::class, 'update'])->name('admin.finansal.puan-paketleri.update');
        Route::delete('finansal/puan-paketleri/{puanPaketi}', [PuanPaketiController::class, 'destroy'])->name('admin.finansal.puan-paketleri.destroy');
        Route::get('finansal/abonelik-paketleri', [AbonelikPaketiController::class, 'index'])->name('admin.finansal.abonelik-paketleri.index');
        Route::get('finansal/abonelik-paketleri/ekle', [AbonelikPaketiController::class, 'create'])->name('admin.finansal.abonelik-paketleri.create');
        Route::post('finansal/abonelik-paketleri', [AbonelikPaketiController::class, 'store'])->name('admin.finansal.abonelik-paketleri.store');
        Route::get('finansal/abonelik-paketleri/{abonelikPaketi}/duzenle', [AbonelikPaketiController::class, 'edit'])->name('admin.finansal.abonelik-paketleri.edit');
        Route::put('finansal/abonelik-paketleri/{abonelikPaketi}', [AbonelikPaketiController::class, 'update'])->name('admin.finansal.abonelik-paketleri.update');
        Route::delete('finansal/abonelik-paketleri/{abonelikPaketi}', [AbonelikPaketiController::class, 'destroy'])->name('admin.finansal.abonelik-paketleri.destroy');
        Route::get('finansal/hediyeler', [HediyeController::class, 'index'])->name('admin.hediyeler.index');
        Route::get('finansal/hediyeler/ekle', [HediyeController::class, 'create'])->name('admin.hediyeler.create');
        Route::post('finansal/hediyeler', [HediyeController::class, 'store'])->name('admin.hediyeler.store');
        Route::get('finansal/hediyeler/{hediye}/duzenle', [HediyeController::class, 'edit'])->name('admin.hediyeler.edit');
        Route::put('finansal/hediyeler/{hediye}', [HediyeController::class, 'update'])->name('admin.hediyeler.update');
        Route::delete('finansal/hediyeler/{hediye}', [HediyeController::class, 'destroy'])->name('admin.hediyeler.destroy');

        // Instagram
        Route::get('instagram', [InstagramController::class, 'index'])->name('admin.instagram.index');
        Route::get('instagram/{instagramHesap}', [InstagramController::class, 'goster'])->name('admin.instagram.goster');
        Route::get('instagram/{instagramHesap}/kisiler', [InstagramController::class, 'kisiler'])->name('admin.instagram.kisiler');
        Route::get('instagram/{instagramHesap}/kisiler/{instagramKisi}/mesajlar', [InstagramController::class, 'mesajlar'])->name('admin.instagram.mesajlar');
        Route::delete('instagram/{instagramHesap}/kisiler/{instagramKisi}/veriler', [InstagramController::class, 'kisiVerileriniSil'])->name('admin.instagram.kisi-verilerini-sil');
        Route::get('instagram/{instagramHesap}/ai-gorevleri', [InstagramController::class, 'aiGorevleri'])->name('admin.instagram.ai-gorevleri');

        // AI Influencer
        Route::get('influencer', [InfluencerController::class, 'index'])->name('admin.influencer.index');
        Route::get('influencer/ekle', [InfluencerController::class, 'ekle'])->name('admin.influencer.ekle');
        Route::post('influencer/ekle', [InfluencerController::class, 'kaydet'])->name('admin.influencer.kaydet');
        Route::get('influencer/{kullanici}', [InfluencerController::class, 'goster'])->name('admin.influencer.goster');
        Route::get('influencer/{kullanici}/duzenle', [InfluencerController::class, 'duzenle'])->name('admin.influencer.duzenle');
        Route::put('influencer/{kullanici}', [InfluencerController::class, 'guncelle'])->name('admin.influencer.guncelle');

        // İstatistik
        Route::get('istatistik', [IstatistikController::class, 'index'])->name('admin.istatistik.index');

        // Ayarlar
        Route::get('ayarlar', [AyarController::class, 'index'])->name('admin.ayarlar');
        Route::post('ayarlar/depolama/nginx-limit-uygula', [AyarController::class, 'nginxUploadLimitiniUygula'])
            ->name('admin.ayarlar.depolama.nginx-limit-uygula');
        Route::get('ayarlar/{kategori}', [AyarController::class, 'show'])->name('admin.ayarlar.kategori');
        Route::put('ayarlar/{kategori}', [AyarController::class, 'guncelle'])->name('admin.ayarlar.kategori.guncelle');
    });
});
