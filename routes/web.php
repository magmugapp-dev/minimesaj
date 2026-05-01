<?php

use App\Http\Controllers\Admin\AbonelikPaketiController;
use App\Http\Controllers\Admin\AiCharacterController;
use App\Http\Controllers\Admin\AiModerationEventController;
use App\Http\Controllers\Admin\AyarController;
use App\Http\Controllers\Admin\DestekTalebiController;
use App\Http\Controllers\Admin\DilMetinController;
use App\Http\Controllers\Admin\EngelController;
use App\Http\Controllers\Admin\EslesmeController;
use App\Http\Controllers\Admin\FinansalController;
use App\Http\Controllers\Admin\GeminiController;
use App\Http\Controllers\Admin\GirisController;
use App\Http\Controllers\Admin\HediyeController;
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
        Route::get('ai', [AiCharacterController::class, 'index'])->name('admin.ai.index');
        Route::get('ai/ekle', [AiCharacterController::class, 'create'])->name('admin.ai.ekle');
        Route::post('ai/ekle', [AiCharacterController::class, 'store'])->name('admin.ai.kaydet');
        Route::get('ai/import', [AiCharacterController::class, 'importForm'])->name('admin.ai.import');
        Route::post('ai/import', [AiCharacterController::class, 'importZip'])->name('admin.ai.import.store');
        Route::get('ai/json-ekle', [AiCharacterController::class, 'importJsonForm'])->name('admin.ai.json-ekle');
        Route::post('ai/json-ekle', [AiCharacterController::class, 'importJsonStore'])->name('admin.ai.json-kaydet');
        Route::post('ai/prompt', [AiCharacterController::class, 'promptUpdate'])->name('admin.ai.prompt.update');
        Route::post('ai/thresholds', [AiCharacterController::class, 'thresholdsUpdate'])->name('admin.ai.thresholds.update');
        Route::get('ai/{character}/duzenle', [AiCharacterController::class, 'edit'])->name('admin.ai.duzenle');
        Route::put('ai/{character}', [AiCharacterController::class, 'update'])->name('admin.ai.guncelle');
        Route::delete('ai/{character}', [AiCharacterController::class, 'destroy'])->name('admin.ai.sil');

        Route::get('gemini', [GeminiController::class, 'index'])->name('admin.gemini.index');
        Route::post('gemini/keys', [GeminiController::class, 'store'])->name('admin.gemini.keys.store');
        Route::put('gemini/keys/{key}', [GeminiController::class, 'update'])->name('admin.gemini.keys.update');
        Route::delete('gemini/keys/{key}', [GeminiController::class, 'destroy'])->name('admin.gemini.keys.destroy');

        // Moderasyon — Şikayetler
        Route::get('moderasyon/sikayetler', [SikayetController::class, 'index'])->name('admin.moderasyon.sikayetler');
        Route::post('moderasyon/sikayetler/toplu-durum', [SikayetController::class, 'topluDurumGuncelle'])->name('admin.moderasyon.sikayetler.toplu-durum');
        Route::get('moderasyon/sikayetler/{sikayet}', [SikayetController::class, 'goster'])->name('admin.moderasyon.sikayetler.goster');
        Route::patch('moderasyon/sikayetler/{sikayet}/durum', [SikayetController::class, 'durumGuncelle'])->name('admin.moderasyon.sikayetler.durum-guncelle');

        // Moderasyon — Engellemeler
        Route::get('moderasyon/engeller', [EngelController::class, 'index'])->name('admin.moderasyon.engeller');
        Route::delete('moderasyon/engeller/{engelleme}', [EngelController::class, 'kaldir'])->name('admin.moderasyon.engeller.kaldir');
        Route::get('moderasyon/ai-olaylari', [AiModerationEventController::class, 'index'])->name('admin.moderasyon.ai-olaylari');

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
