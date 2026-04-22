<?php

namespace Database\Seeders;

use App\Models\Ayar;
use Illuminate\Database\Seeder;

class AyarSeeder extends Seeder
{
    public function run(): void
    {
        $ayarlar = [
            // ── Genel ──────────────────────────────────────────────
            ['anahtar' => 'site_adi', 'deger' => 'MiniMesaj', 'grup' => 'genel', 'tip' => 'string', 'aciklama' => 'Site Adı'],
            ['anahtar' => 'site_url', 'deger' => 'https://minimesaj.com', 'grup' => 'genel', 'tip' => 'string', 'aciklama' => 'Site URL'],
            ['anahtar' => 'bakim_modu', 'deger' => '0', 'grup' => 'genel', 'tip' => 'boolean', 'aciklama' => 'Bakım Modu'],
            ['anahtar' => 'varsayilan_dil', 'deger' => 'tr', 'grup' => 'genel', 'tip' => 'string', 'aciklama' => 'Varsayılan Dil'],
            ['anahtar' => 'kayit_aktif_mi', 'deger' => '1', 'grup' => 'genel', 'tip' => 'boolean', 'aciklama' => 'Kayıt Aktif mi'],
            ['anahtar' => 'uygulama_versiyonu', 'deger' => '1.0.0', 'grup' => 'genel', 'tip' => 'string', 'aciklama' => 'Uygulama Versiyonu'],
            ['anahtar' => 'mobil_minimum_versiyon', 'deger' => '1.0.0', 'grup' => 'genel', 'tip' => 'string', 'aciklama' => 'Mobil Minimum Versiyon'],
            ['anahtar' => 'destek_eposta', 'deger' => '', 'grup' => 'genel', 'tip' => 'string', 'aciklama' => 'Destek E-posta Adresi'],
            ['anahtar' => 'destek_whatsapp', 'deger' => '', 'grup' => 'genel', 'tip' => 'string', 'aciklama' => 'Destek WhatsApp Numarası'],
            ['anahtar' => 'uygulama_logosu', 'deger' => '', 'grup' => 'genel', 'tip' => 'file', 'aciklama' => 'Uygulama Logosu'],
            ['anahtar' => 'flutter_logosu', 'deger' => '', 'grup' => 'genel', 'tip' => 'file', 'aciklama' => 'Flutter Uygulama Logosu (API: /api/uygulama/logo)'],

            // ── AI Sağlayıcılar ────────────────────────────────────
            ['anahtar' => 'openai_api_key', 'deger' => '', 'grup' => 'ai_saglayicilar', 'tip' => 'string', 'aciklama' => 'OpenAI API Key'],
            ['anahtar' => 'openai_varsayilan_model', 'deger' => 'gpt-4o', 'grup' => 'ai_saglayicilar', 'tip' => 'string', 'aciklama' => 'OpenAI Varsayılan Model'],
            ['anahtar' => 'gemini_api_key', 'deger' => '', 'grup' => 'ai_saglayicilar', 'tip' => 'string', 'aciklama' => 'Gemini API Key'],
            ['anahtar' => 'gemini_varsayilan_model', 'deger' => 'gemini-2.5-pro', 'grup' => 'ai_saglayicilar', 'tip' => 'string', 'aciklama' => 'Gemini Varsayılan Model'],
            ['anahtar' => 'varsayilan_ai_saglayici', 'deger' => 'gemini', 'grup' => 'ai_saglayicilar', 'tip' => 'string', 'aciklama' => 'Varsayılan AI Sağlayıcı'],
            ['anahtar' => 'yedek_ai_saglayici', 'deger' => 'openai', 'grup' => 'ai_saglayicilar', 'tip' => 'string', 'aciklama' => 'Yedek AI Sağlayıcı'],
            ['anahtar' => 'ai_max_token', 'deger' => '1024', 'grup' => 'ai_saglayicilar', 'tip' => 'integer', 'aciklama' => 'AI Maks Token'],
            ['anahtar' => 'ai_temperature', 'deger' => '0.7', 'grup' => 'ai_saglayicilar', 'tip' => 'string', 'aciklama' => 'AI Temperature'],

            // ── Apple ──────────────────────────────────────────────
            ['anahtar' => 'apple_issuer_id', 'deger' => '', 'grup' => 'apple', 'tip' => 'string', 'aciklama' => 'Apple Issuer ID'],
            ['anahtar' => 'apple_key_id', 'deger' => '', 'grup' => 'apple', 'tip' => 'string', 'aciklama' => 'Apple Key ID'],
            ['anahtar' => 'apple_private_key_path', 'deger' => '', 'grup' => 'apple', 'tip' => 'file', 'aciklama' => 'Apple Private Key Dosyası'],
            ['anahtar' => 'apple_bundle_id', 'deger' => '', 'grup' => 'apple', 'tip' => 'string', 'aciklama' => 'Apple Bundle ID'],
            ['anahtar' => 'apple_sandbox', 'deger' => '1', 'grup' => 'apple', 'tip' => 'boolean', 'aciklama' => 'Apple Sandbox Modu'],
            ['anahtar' => 'apple_odeme_aktif_mi', 'deger' => '0', 'grup' => 'apple', 'tip' => 'boolean', 'aciklama' => 'App Store odemeleri aktif mi'],
            ['anahtar' => 'ios_app_store_url', 'deger' => '', 'grup' => 'apple', 'tip' => 'string', 'aciklama' => 'App Store Uygulama Linki'],
            ['anahtar' => 'google_auth_ios_client_id', 'deger' => '', 'grup' => 'google_auth', 'tip' => 'string', 'aciklama' => 'Google iOS Client ID'],
            ['anahtar' => 'google_auth_android_client_id', 'deger' => '', 'grup' => 'google_auth', 'tip' => 'string', 'aciklama' => 'Google Android Client ID'],
            ['anahtar' => 'google_auth_server_client_id', 'deger' => '', 'grup' => 'google_auth', 'tip' => 'string', 'aciklama' => 'Google Server Client ID'],

            // ── Google Play ────────────────────────────────────────
            ['anahtar' => 'google_play_paket_adi', 'deger' => '', 'grup' => 'google_play', 'tip' => 'string', 'aciklama' => 'Google Play Paket Adı'],
            ['anahtar' => 'google_play_service_account_path', 'deger' => '', 'grup' => 'google_play', 'tip' => 'file', 'aciklama' => 'Google Play Service Account JSON'],
            ['anahtar' => 'google_play_odeme_aktif_mi', 'deger' => '0', 'grup' => 'google_play', 'tip' => 'boolean', 'aciklama' => 'Google Play odemeleri aktif mi'],
            ['anahtar' => 'android_play_store_url', 'deger' => 'https://play.google.com/store/apps/details?id=com.magmug.magmug', 'grup' => 'google_play', 'tip' => 'string', 'aciklama' => 'Play Store Uygulama Linki'],

            // ── Puan Sistemi ───────────────────────────────────────
            ['anahtar' => 'kayit_puani', 'deger' => '100', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Kayıt Bonusu Puanı'],
            ['anahtar' => 'gunluk_giris_puani', 'deger' => '10', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Günlük Giriş Puanı'],
            ['anahtar' => 'eslesme_baslatma_maliyeti', 'deger' => '8', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Eşleşme Başlatma Maliyeti'],
            ['anahtar' => 'eslesme_odulu', 'deger' => '20', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Eşleşme Ödül Puanı'],
            ['anahtar' => 'reklam_odulu', 'deger' => '15', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Reklam İzleme Ödülü'],
            ['anahtar' => 'hediye_puan_katsayisi', 'deger' => '2', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Hediye Puan Katsayısı'],
            ['anahtar' => 'profil_one_cikarma_maliyeti', 'deger' => '50', 'grup' => 'puan_sistemi', 'tip' => 'integer', 'aciklama' => 'Profil Öne Çıkarma Maliyeti'],

            // ── Premium ────────────────────────────────────────────
            ['anahtar' => 'premium_aylik_fiyat', 'deger' => '49.99', 'grup' => 'premium', 'tip' => 'string', 'aciklama' => 'Premium Aylık Fiyat (₺)'],
            ['anahtar' => 'premium_yillik_fiyat', 'deger' => '399.99', 'grup' => 'premium', 'tip' => 'string', 'aciklama' => 'Premium Yıllık Fiyat (₺)'],
            ['anahtar' => 'premium_gunluk_hak', 'deger' => '999', 'grup' => 'premium', 'tip' => 'integer', 'aciklama' => 'Premium Günlük Hak'],
            ['anahtar' => 'premium_ozellikler', 'deger' => '["okundu_bilgisi","profil_one_cikarma"]', 'grup' => 'premium', 'tip' => 'json', 'aciklama' => 'Premium Özellikler'],

            // ── Limitler ───────────────────────────────────────────
            ['anahtar' => 'gunluk_ucretsiz_hak', 'deger' => '3', 'grup' => 'limitler', 'tip' => 'integer', 'aciklama' => 'Günlük Ücretsiz Eşleşme Hakkı'],
            ['anahtar' => 'foto_limit', 'deger' => '6', 'grup' => 'limitler', 'tip' => 'integer', 'aciklama' => 'Maksimum Fotoğraf Sayısı'],
            ['anahtar' => 'mesaj_uzunluk_max', 'deger' => '1000', 'grup' => 'limitler', 'tip' => 'integer', 'aciklama' => 'Maksimum Mesaj Uzunluğu'],
            ['anahtar' => 'biyografi_uzunluk_max', 'deger' => '500', 'grup' => 'limitler', 'tip' => 'integer', 'aciklama' => 'Maksimum Biyografi Uzunluğu'],
            ['anahtar' => 'gunluk_mesaj_limiti', 'deger' => '200', 'grup' => 'limitler', 'tip' => 'integer', 'aciklama' => 'Günlük Mesaj Limiti'],
            ['anahtar' => 'max_engelleme_sayisi', 'deger' => '100', 'grup' => 'limitler', 'tip' => 'integer', 'aciklama' => 'Maksimum Engelleme Sayısı'],

            // ── Moderasyon ─────────────────────────────────────────
            ['anahtar' => 'oto_yasaklama_esigi', 'deger' => '5', 'grup' => 'moderasyon', 'tip' => 'integer', 'aciklama' => 'Otomatik Yasaklama Şikayet Eşiği'],
            ['anahtar' => 'sikayet_kontrol_suresi_saat', 'deger' => '48', 'grup' => 'moderasyon', 'tip' => 'integer', 'aciklama' => 'Şikayet Kontrol Süresi (saat)'],
            ['anahtar' => 'yasakli_kelimeler', 'deger' => '[]', 'grup' => 'moderasyon', 'tip' => 'json', 'aciklama' => 'Yasaklı Kelimeler'],
            ['anahtar' => 'icerik_filtreleme_aktif', 'deger' => '1', 'grup' => 'moderasyon', 'tip' => 'boolean', 'aciklama' => 'İçerik Filtreleme Aktif'],
            ['anahtar' => 'spam_koruma_aktif', 'deger' => '1', 'grup' => 'moderasyon', 'tip' => 'boolean', 'aciklama' => 'Spam Koruma Aktif'],

            // ── Bildirimler ────────────────────────────────────────
            ['anahtar' => 'firebase_server_key', 'deger' => '', 'grup' => 'bildirimler', 'tip' => 'string', 'aciklama' => 'Firebase Server Key (Legacy - Kullanilmiyor)'],
            ['anahtar' => 'firebase_project_id', 'deger' => '', 'grup' => 'bildirimler', 'tip' => 'string', 'aciklama' => 'Firebase Project ID'],
            ['anahtar' => 'firebase_service_account_path', 'deger' => '', 'grup' => 'bildirimler', 'tip' => 'file', 'aciklama' => 'Firebase Service Account JSON'],
            ['anahtar' => 'apns_sertifika_yolu', 'deger' => '', 'grup' => 'bildirimler', 'tip' => 'file', 'aciklama' => 'APNs Sertifika Dosyası'],
            ['anahtar' => 'bildirim_esleme', 'deger' => '1', 'grup' => 'bildirimler', 'tip' => 'boolean', 'aciklama' => 'Eşleşme Bildirimi'],
            ['anahtar' => 'bildirim_mesaj', 'deger' => '1', 'grup' => 'bildirimler', 'tip' => 'boolean', 'aciklama' => 'Mesaj Bildirimi'],
            ['anahtar' => 'bildirim_hediye', 'deger' => '1', 'grup' => 'bildirimler', 'tip' => 'boolean', 'aciklama' => 'Hediye Bildirimi'],

            // ── E-posta ────────────────────────────────────────────
            ['anahtar' => 'smtp_host', 'deger' => 'smtp.mailtrap.io', 'grup' => 'eposta', 'tip' => 'string', 'aciklama' => 'SMTP Sunucu'],
            ['anahtar' => 'smtp_port', 'deger' => '587', 'grup' => 'eposta', 'tip' => 'integer', 'aciklama' => 'SMTP Port'],
            ['anahtar' => 'smtp_kullanici', 'deger' => '', 'grup' => 'eposta', 'tip' => 'string', 'aciklama' => 'SMTP Kullanıcı'],
            ['anahtar' => 'smtp_sifre', 'deger' => '', 'grup' => 'eposta', 'tip' => 'string', 'aciklama' => 'SMTP Şifre'],
            ['anahtar' => 'smtp_sifreleme', 'deger' => 'tls', 'grup' => 'eposta', 'tip' => 'string', 'aciklama' => 'SMTP Şifreleme (tls/ssl)'],
            ['anahtar' => 'gonderen_email', 'deger' => 'no-reply@minimesaj.com', 'grup' => 'eposta', 'tip' => 'string', 'aciklama' => 'Gönderen E-posta'],
            ['anahtar' => 'gonderen_adi', 'deger' => 'MiniMesaj', 'grup' => 'eposta', 'tip' => 'string', 'aciklama' => 'Gönderen Adı'],

            // ── Güvenlik ───────────────────────────────────────────
            ['anahtar' => 'token_suresi_dakika', 'deger' => '10080', 'grup' => 'guvenlik', 'tip' => 'integer', 'aciklama' => 'Token Süresi (dakika)'],
            ['anahtar' => 'max_giris_denemesi', 'deger' => '5', 'grup' => 'guvenlik', 'tip' => 'integer', 'aciklama' => 'Maksimum Giriş Denemesi'],
            ['anahtar' => 'kilit_suresi_dakika', 'deger' => '15', 'grup' => 'guvenlik', 'tip' => 'integer', 'aciklama' => 'Kilit Süresi (dakika)'],
            ['anahtar' => 'iki_faktor_aktif', 'deger' => '0', 'grup' => 'guvenlik', 'tip' => 'boolean', 'aciklama' => 'İki Faktör Doğrulama'],
            ['anahtar' => 'ip_kara_liste', 'deger' => '[]', 'grup' => 'guvenlik', 'tip' => 'json', 'aciklama' => 'IP Kara Liste'],

            // ── Depolama ───────────────────────────────────────────
            ['anahtar' => 'depolama_disk', 'deger' => 'public', 'grup' => 'depolama', 'tip' => 'string', 'aciklama' => 'Depolama Diski'],
            ['anahtar' => 's3_key', 'deger' => '', 'grup' => 'depolama', 'tip' => 'string', 'aciklama' => 'S3 Access Key'],
            ['anahtar' => 's3_secret', 'deger' => '', 'grup' => 'depolama', 'tip' => 'string', 'aciklama' => 'S3 Secret Key'],
            ['anahtar' => 's3_bucket', 'deger' => '', 'grup' => 'depolama', 'tip' => 'string', 'aciklama' => 'S3 Bucket'],
            ['anahtar' => 's3_region', 'deger' => 'eu-central-1', 'grup' => 'depolama', 'tip' => 'string', 'aciklama' => 'S3 Region'],
            ['anahtar' => 'depolama_fotograf_dizini', 'deger' => 'fotograflar', 'grup' => 'depolama', 'tip' => 'string', 'aciklama' => 'Fotograf Klasor Dizin Adi'],
            ['anahtar' => 'depolama_video_dizini', 'deger' => 'videolar', 'grup' => 'depolama', 'tip' => 'string', 'aciklama' => 'Video Klasor Dizin Adi'],
            ['anahtar' => 'izinli_fotograf_uzantilari', 'deger' => 'jpg,jpeg,png,gif,webp,heic,heif,bmp,svg', 'grup' => 'depolama', 'tip' => 'string', 'aciklama' => 'Izinli fotograf uzantilari (virgulle)'],
            ['anahtar' => 'izinli_video_uzantilari', 'deger' => 'mp4,mov,avi,webm,m4v,3gp,mkv,flv,wmv', 'grup' => 'depolama', 'tip' => 'string', 'aciklama' => 'Izinli video uzantilari (virgulle)'],
            ['anahtar' => 'max_foto_boyut_mb', 'deger' => '5', 'grup' => 'depolama', 'tip' => 'integer', 'aciklama' => 'Maksimum Fotoğraf Boyutu (MB)'],
            ['anahtar' => 'max_video_boyut_mb', 'deger' => '100', 'grup' => 'depolama', 'tip' => 'integer', 'aciklama' => 'Maksimum Video Boyutu (MB)'],
            ['anahtar' => 'nginx_max_body_mb', 'deger' => '100', 'grup' => 'depolama', 'tip' => 'integer', 'aciklama' => 'Nginx Maksimum Upload Boyutu (MB)'],

            // ── WebSocket ──────────────────────────────────────────
            ['anahtar' => 'reverb_app_id', 'deger' => '', 'grup' => 'websocket', 'tip' => 'string', 'aciklama' => 'Reverb App ID'],
            ['anahtar' => 'reverb_key', 'deger' => '', 'grup' => 'websocket', 'tip' => 'string', 'aciklama' => 'Reverb Key'],
            ['anahtar' => 'reverb_secret', 'deger' => '', 'grup' => 'websocket', 'tip' => 'string', 'aciklama' => 'Reverb Secret'],
            ['anahtar' => 'reverb_host', 'deger' => 'localhost', 'grup' => 'websocket', 'tip' => 'string', 'aciklama' => 'Reverb Host'],
            ['anahtar' => 'reverb_port', 'deger' => '8080', 'grup' => 'websocket', 'tip' => 'integer', 'aciklama' => 'Reverb Port'],

            // ── Rate Limiting ──────────────────────────────────────
            ['anahtar' => 'api_dakika_limit', 'deger' => '60', 'grup' => 'rate_limiting', 'tip' => 'integer', 'aciklama' => 'API İstek Limiti (/dakika)'],
            ['anahtar' => 'auth_dakika_limit', 'deger' => '10', 'grup' => 'rate_limiting', 'tip' => 'integer', 'aciklama' => 'Auth İstek Limiti (/dakika)'],
            ['anahtar' => 'ai_dakika_limit', 'deger' => '20', 'grup' => 'rate_limiting', 'tip' => 'integer', 'aciklama' => 'AI İstek Limiti (/dakika)'],
            ['anahtar' => 'instagram_dakika_limit', 'deger' => '30', 'grup' => 'rate_limiting', 'tip' => 'integer', 'aciklama' => 'Instagram İstek Limiti (/dakika)'],

            // ── Eşleştirme ─────────────────────────────────────────
            ['anahtar' => 'eslestirme_algoritma', 'deger' => 'yakin_konum', 'grup' => 'eslestirme', 'tip' => 'string', 'aciklama' => 'Eşleştirme Algoritması'],
            ['anahtar' => 'mesafe_km_max', 'deger' => '100', 'grup' => 'eslestirme', 'tip' => 'integer', 'aciklama' => 'Maksimum Mesafe (km)'],
            ['anahtar' => 'yas_farki_max', 'deger' => '10', 'grup' => 'eslestirme', 'tip' => 'integer', 'aciklama' => 'Maksimum Yaş Farkı'],
            ['anahtar' => 'ai_eslestirme_aktif', 'deger' => '1', 'grup' => 'eslestirme', 'tip' => 'boolean', 'aciklama' => 'AI ile Eşleştirme Aktif'],
            ['anahtar' => 'one_cikarma_suresi_saat', 'deger' => '24', 'grup' => 'eslestirme', 'tip' => 'integer', 'aciklama' => 'Profil Öne Çıkarma Süresi (saat)'],

            // ── Instagram ──────────────────────────────────────────
            ['anahtar' => 'instagram_api_url', 'deger' => 'https://www.instagram.com', 'grup' => 'instagram', 'tip' => 'string', 'aciklama' => 'Instagram API URL'],
            ['anahtar' => 'instagram_webhook_secret', 'deger' => '', 'grup' => 'instagram', 'tip' => 'string', 'aciklama' => 'Instagram Webhook Secret'],
            ['anahtar' => 'instagram_senkron_araligi_dakika', 'deger' => '5', 'grup' => 'instagram', 'tip' => 'integer', 'aciklama' => 'Instagram Senkron Aralığı (dakika)'],
            ['anahtar' => 'instagram_max_hesap_kullanici', 'deger' => '3', 'grup' => 'instagram', 'tip' => 'integer', 'aciklama' => 'Kullanıcı Başı Maks Instagram Hesabı'],
        ];

        foreach ($ayarlar as $ayar) {
            Ayar::firstOrCreate(
                ['anahtar' => $ayar['anahtar']],
                $ayar
            );
        }
    }
}
