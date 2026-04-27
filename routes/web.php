<?php

use App\Http\Controllers\Admin\AbonelikPaketiController;
use App\Http\Controllers\Admin\AiController;
use App\Http\Controllers\Admin\AiPhotoController;
use App\Http\Controllers\Admin\AiStudioController;
use App\Http\Controllers\Admin\AyarController;
use App\Http\Controllers\Admin\DestekTalebiController;
use App\Http\Controllers\Admin\DilMetinController;
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
        Route::get('ai', [AiStudioController::class, 'index'])->name('admin.ai.index');
        Route::post('ai/motor', [AiStudioController::class, 'engineUpdate'])->name('admin.ai.engine.update');
        Route::get('ai/states', [AiStudioController::class, 'states'])->name('admin.ai.states');
        Route::get('ai/memories', [AiStudioController::class, 'memories'])->name('admin.ai.memories');
        Route::get('ai/traces', [AiStudioController::class, 'traces'])->name('admin.ai.traces');
        Route::get('ai/fotograflar', [AiPhotoController::class, 'index'])->name('admin.ai.fotograflar');
        Route::post('ai/fotograflar', [AiPhotoController::class, 'store'])->name('admin.ai.fotograflar.store');
        Route::get('ai/ekle', [AiStudioController::class, 'create'])->name('admin.ai.ekle');
        Route::post('ai/ekle', [AiStudioController::class, 'store'])->name('admin.ai.kaydet');
        Route::get('ai/json-ekle', [AiController::class, 'jsonEkle'])->name('admin.ai.json-ekle');
        Route::post('ai/json-ekle', [AiController::class, 'jsonKaydet'])->name('admin.ai.json-kaydet');
        Route::post('ai/toplu-durum', [AiController::class, 'topluDurumGuncelle'])->name('admin.ai.toplu-durum');
        Route::post('ai/{kullanici}/fotograflar', [AiPhotoController::class, 'storeForUser'])->name('admin.ai.fotograflar.user-store');
        Route::patch('ai/{kullanici}/fotograflar/{fotograf}', [AiPhotoController::class, 'update'])->name('admin.ai.fotograflar.update');
        Route::delete('ai/{kullanici}/fotograflar/{fotograf}', [AiPhotoController::class, 'destroy'])->name('admin.ai.fotograflar.destroy');
        Route::get('ai/{kullanici}', [AiStudioController::class, 'show'])->name('admin.ai.goster');
        Route::get('ai/{kullanici}/duzenle', [AiStudioController::class, 'edit'])->name('admin.ai.duzenle');
        Route::put('ai/{kullanici}', [AiStudioController::class, 'update'])->name('admin.ai.guncelle');
        Route::delete('ai/{kullanici}', [AiStudioController::class, 'destroy'])->name('admin.ai.sil');

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
        Route::get('influencer/json-ekle', [InfluencerController::class, 'jsonEkle'])->name('admin.influencer.json-ekle');
        Route::post('influencer/json-ekle', [InfluencerController::class, 'jsonKaydet'])->name('admin.influencer.json-kaydet');
        Route::get('influencer/{kullanici}', [InfluencerController::class, 'goster'])->name('admin.influencer.goster');
        Route::get('influencer/{kullanici}/duzenle', [InfluencerController::class, 'duzenle'])->name('admin.influencer.duzenle');
        Route::put('influencer/{kullanici}', [InfluencerController::class, 'guncelle'])->name('admin.influencer.guncelle');
        Route::delete('influencer/{kullanici}', [InfluencerController::class, 'destroy'])->name('admin.influencer.sil');

        // İstatistik
        Route::get('istatistik', [IstatistikController::class, 'index'])->name('admin.istatistik.index');

        // Dil ve Metin Yonetimi
        Route::get('dil-metin', [DilMetinController::class, 'index'])->name('admin.dil-metin.index');
        Route::prefix('dil-metin/api')->name('admin.dil-metin.api.')->group(function (): void {
            Route::get('meta', [DilMetinController::class, 'apiMeta'])->name('meta');

            Route::get('keyler', [DilMetinController::class, 'apiTranslationKeysIndex'])->name('keys.index');
            Route::post('keyler', [DilMetinController::class, 'apiTranslationKeyStore'])->name('keys.store');
            Route::get('keyler/{translationKey}', [DilMetinController::class, 'apiTranslationKeyShow'])->name('keys.show');
            Route::put('keyler/{translationKey}', [DilMetinController::class, 'apiTranslationKeyUpdate'])->name('keys.update');
            Route::put('keyler/{translationKey}/ceviriler', [DilMetinController::class, 'apiTranslationsUpdate'])->name('keys.translations.update');
            Route::delete('keyler/{translationKey}', [DilMetinController::class, 'apiTranslationKeyDestroy'])->name('keys.destroy');
            Route::patch('keyler/{translationKey}/geri-al', [DilMetinController::class, 'apiTranslationKeyRestore'])->name('keys.restore');
            Route::delete('keyler/{translationKey}/kalici-sil', [DilMetinController::class, 'apiTranslationKeyForceDestroy'])->name('keys.force-destroy');

            Route::get('diller', [DilMetinController::class, 'apiLanguagesIndex'])->name('languages.index');
            Route::post('diller', [DilMetinController::class, 'apiLanguageStore'])->name('languages.store');
            Route::get('diller/{language}', [DilMetinController::class, 'apiLanguageShow'])->name('languages.show');
            Route::put('diller/{language}', [DilMetinController::class, 'apiLanguageUpdate'])->name('languages.update');
            Route::patch('diller/{language}/varsayilan', [DilMetinController::class, 'apiLanguageMakeDefault'])->name('languages.default');
            Route::delete('diller/{language}', [DilMetinController::class, 'apiLanguageDestroy'])->name('languages.destroy');
            Route::patch('diller/{language}/geri-al', [DilMetinController::class, 'apiLanguageRestore'])->name('languages.restore');
            Route::delete('diller/{language}/kalici-sil', [DilMetinController::class, 'apiLanguageForceDestroy'])->name('languages.force-destroy');

            Route::get('yasal-metinler', [DilMetinController::class, 'apiLegalDocumentsIndex'])->name('legal.index');
            Route::post('yasal-metinler', [DilMetinController::class, 'apiLegalDocumentStore'])->name('legal.store');
            Route::get('yasal-metinler/{legalDocument}', [DilMetinController::class, 'apiLegalDocumentShow'])->name('legal.show');
            Route::put('yasal-metinler/{legalDocument}', [DilMetinController::class, 'apiLegalDocumentUpdate'])->name('legal.update');
            Route::delete('yasal-metinler/{legalDocument}', [DilMetinController::class, 'apiLegalDocumentDestroy'])->name('legal.destroy');
            Route::patch('yasal-metinler/{legalDocument}/geri-al', [DilMetinController::class, 'apiLegalDocumentRestore'])->name('legal.restore');
            Route::delete('yasal-metinler/{legalDocument}/kalici-sil', [DilMetinController::class, 'apiLegalDocumentForceDestroy'])->name('legal.force-destroy');

            Route::get('faq', [DilMetinController::class, 'apiFaqItemsIndex'])->name('faq.index');
            Route::post('faq', [DilMetinController::class, 'apiFaqItemStore'])->name('faq.store');
            Route::get('faq/{faqItem}', [DilMetinController::class, 'apiFaqItemShow'])->name('faq.show');
            Route::put('faq/{faqItem}', [DilMetinController::class, 'apiFaqItemUpdate'])->name('faq.update');
            Route::delete('faq/{faqItem}', [DilMetinController::class, 'apiFaqItemDestroy'])->name('faq.destroy');
            Route::patch('faq/{faqItem}/geri-al', [DilMetinController::class, 'apiFaqItemRestore'])->name('faq.restore');
            Route::delete('faq/{faqItem}/kalici-sil', [DilMetinController::class, 'apiFaqItemForceDestroy'])->name('faq.force-destroy');
        });
        Route::post('dil-metin/diller', [DilMetinController::class, 'storeLanguage'])->name('admin.dil-metin.languages.store');
        Route::put('dil-metin/diller/{language}', [DilMetinController::class, 'updateLanguage'])->name('admin.dil-metin.languages.update');
        Route::patch('dil-metin/diller/{language}/varsayilan', [DilMetinController::class, 'makeDefaultLanguage'])->name('admin.dil-metin.languages.default');
        Route::delete('dil-metin/diller/{language}', [DilMetinController::class, 'destroyLanguage'])->name('admin.dil-metin.languages.destroy');
        Route::patch('dil-metin/diller/{language}/geri-al', [DilMetinController::class, 'restoreLanguage'])->name('admin.dil-metin.languages.restore');
        Route::delete('dil-metin/diller/{language}/kalici-sil', [DilMetinController::class, 'forceDestroyLanguage'])->name('admin.dil-metin.languages.force-destroy');
        Route::post('dil-metin/keyler', [DilMetinController::class, 'storeTranslationKey'])->name('admin.dil-metin.keys.store');
        Route::patch('dil-metin/keyler/toplu-arsivle', [DilMetinController::class, 'bulkArchiveTranslationKeys'])->name('admin.dil-metin.keys.bulk-archive');
        Route::put('dil-metin/keyler/{translationKey}', [DilMetinController::class, 'updateTranslationKey'])->name('admin.dil-metin.keys.update');
        Route::delete('dil-metin/keyler/{translationKey}', [DilMetinController::class, 'destroyTranslationKey'])->name('admin.dil-metin.keys.destroy');
        Route::patch('dil-metin/keyler/{translationKey}/geri-al', [DilMetinController::class, 'restoreTranslationKey'])->name('admin.dil-metin.keys.restore');
        Route::delete('dil-metin/keyler/{translationKey}/kalici-sil', [DilMetinController::class, 'forceDestroyTranslationKey'])->name('admin.dil-metin.keys.force-destroy');
        Route::put('dil-metin/keyler/{translationKey}/ceviri', [DilMetinController::class, 'updateTranslation'])->name('admin.dil-metin.translations.update');
        Route::put('dil-metin/yasal-metin', [DilMetinController::class, 'updateLegalDocument'])->name('admin.dil-metin.legal.update');
        Route::delete('dil-metin/yasal-metin/{legalDocument}', [DilMetinController::class, 'destroyLegalDocument'])->name('admin.dil-metin.legal.destroy');
        Route::patch('dil-metin/yasal-metin/{legalDocument}/geri-al', [DilMetinController::class, 'restoreLegalDocument'])->name('admin.dil-metin.legal.restore');
        Route::delete('dil-metin/yasal-metin/{legalDocument}/kalici-sil', [DilMetinController::class, 'forceDestroyLegalDocument'])->name('admin.dil-metin.legal.force-destroy');
        Route::post('dil-metin/faq', [DilMetinController::class, 'storeFaq'])->name('admin.dil-metin.faq.store');
        Route::put('dil-metin/faq/{faqItem}', [DilMetinController::class, 'updateFaq'])->name('admin.dil-metin.faq.update');
        Route::delete('dil-metin/faq/{faqItem}', [DilMetinController::class, 'destroyFaq'])->name('admin.dil-metin.faq.destroy');
        Route::patch('dil-metin/faq/{faqItem}/geri-al', [DilMetinController::class, 'restoreFaq'])->name('admin.dil-metin.faq.restore');
        Route::delete('dil-metin/faq/{faqItem}/kalici-sil', [DilMetinController::class, 'forceDestroyFaq'])->name('admin.dil-metin.faq.force-destroy');

        // Ayarlar
        Route::get('ayarlar', [AyarController::class, 'index'])->name('admin.ayarlar');
        Route::post('ayarlar/depolama/nginx-limit-uygula', [AyarController::class, 'nginxUploadLimitiniUygula'])
            ->name('admin.ayarlar.depolama.nginx-limit-uygula');
        Route::get('ayarlar/{kategori}', [AyarController::class, 'show'])->name('admin.ayarlar.kategori');
        Route::put('ayarlar/{kategori}', [AyarController::class, 'guncelle'])->name('admin.ayarlar.kategori.guncelle');
    });
});
