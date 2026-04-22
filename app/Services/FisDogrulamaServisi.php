<?php

namespace App\Services;

use App\Services\Odeme\MobilOdemeAyarServisi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FisDogrulamaServisi
{
    public function __construct(private MobilOdemeAyarServisi $mobilOdemeAyarServisi) {}

    /**
     * Platform bazlı fiş doğrulaması yapar.
     *
     * @return array{gecerli: bool, islem_kodu: ?string, hata: ?string}
     */
    public function dogrula(string $platform, string $fisVerisi, string $urunKodu, string $urunTipi = 'tek_seferlik'): array
    {
        if (!$this->mobilOdemeAyarServisi->kanalAktifMi($platform)) {
            return [
                'gecerli' => false,
                'islem_kodu' => null,
                'hata' => 'Secilen mobil odeme kanali panelde pasif durumda.',
            ];
        }

        return match ($platform) {
            'ios' => $this->appleDogrula($fisVerisi, $urunKodu),
            'android' => $this->googleDogrula($fisVerisi, $urunKodu, $urunTipi),
            default => ['gecerli' => false, 'islem_kodu' => null, 'hata' => 'Gecersiz platform.'],
        };
    }

    private function appleIslemKimligiBul(string $fisVerisi): ?string
    {
        $duzenlenmis = trim($fisVerisi);
        if ($duzenlenmis === '') {
            return null;
        }

        $jsonVeri = json_decode($duzenlenmis, true);
        if (is_array($jsonVeri)) {
            $islemKimligi = $jsonVeri['transactionId'] ?? $jsonVeri['originalTransactionId'] ?? null;

            if (is_scalar($islemKimligi) && trim((string) $islemKimligi) !== '') {
                return trim((string) $islemKimligi);
            }
        }

        $jwsAlanlari = $this->appleJwsAlanlariniCoz($duzenlenmis);
        $islemKimligi = $jwsAlanlari['transactionId'] ?? $jwsAlanlari['originalTransactionId'] ?? null;

        if (is_scalar($islemKimligi) && trim((string) $islemKimligi) !== '') {
            return trim((string) $islemKimligi);
        }

        return $duzenlenmis;
    }

    private function appleJwsAlanlariniCoz(string $jws): array
    {
        if (!str_contains($jws, '.')) {
            return [];
        }

        $parcalar = explode('.', $jws);
        if (count($parcalar) < 2) {
            return [];
        }

        $payload = $this->base64UrlDecode($parcalar[1]);
        if ($payload === null) {
            return [];
        }

        $veri = json_decode($payload, true);

        return is_array($veri) ? $veri : [];
    }

    private function base64UrlDecode(string $deger): ?string
    {
        $duzenlenmis = strtr($deger, '-_', '+/');
        $padding = strlen($duzenlenmis) % 4;

        if ($padding > 0) {
            $duzenlenmis .= str_repeat('=', 4 - $padding);
        }

        $cozulmus = base64_decode($duzenlenmis, true);

        return $cozulmus === false ? null : $cozulmus;
    }

    /**
     * Apple App Store Server API v2 ile doğrulama.
     * https://developer.apple.com/documentation/appstoreserverapi
     */
    private function appleDogrula(string $fisVerisi, string $urunKodu): array
    {
        $ayarlar = $this->mobilOdemeAyarServisi->appleAyarlari();

        if (!$this->mobilOdemeAyarServisi->appleHazirMi()) {
            return [
                'gecerli' => false,
                'islem_kodu' => null,
                'hata' => 'Apple odeme ayarlari panelde eksik.',
            ];
        }

        $ortam = ($ayarlar['sandbox'] ?? true) ? 'sandbox' : 'production';
        $temelUrl = $ortam === 'sandbox'
            ? 'https://api.storekit-sandbox.itunes.apple.com'
            : 'https://api.storekit.itunes.apple.com';
        $islemKimligi = $this->appleIslemKimligiBul($fisVerisi);

        if (!$islemKimligi) {
            return [
                'gecerli' => false,
                'islem_kodu' => null,
                'hata' => 'Apple iÅŸlem kimliÄŸi okunamadÄ±.',
            ];
        }

        try {
            $jwt = $this->appleJwtOlustur($ayarlar);

            $yanit = Http::withToken($jwt)
                ->timeout(15)
                ->get("{$temelUrl}/inApps/v1/transactions/{$islemKimligi}");

            if ($yanit->failed()) {
                Log::warning('Apple fiş doğrulama başarısız', [
                    'durum' => $yanit->status(),
                    'yanit' => $yanit->body(),
                ]);

                return [
                    'gecerli' => false,
                    'islem_kodu' => null,
                    'hata' => 'Apple doğrulama başarısız: HTTP ' . $yanit->status(),
                ];
            }

            $veri = $yanit->json();
            $islemKodu = $veri['transactionId'] ?? $islemKimligi;

            // Ürün kodu kontrolü
            if (isset($veri['productId']) && $veri['productId'] !== $urunKodu) {
                return [
                    'gecerli' => false,
                    'islem_kodu' => $islemKodu,
                    'hata' => 'Urun kodu uyusmuyor.',
                ];
            }

            return [
                'gecerli' => true,
                'islem_kodu' => $islemKodu,
                'hata' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Apple fiş doğrulama hatası', ['hata' => $e->getMessage()]);

            return [
                'gecerli' => false,
                'islem_kodu' => null,
                'hata' => 'Apple sunucusuna bağlanılamadı.',
            ];
        }
    }

    /**
     * Google Play Developer API ile doğrulama.
     * https://developers.google.com/android-publisher/api-ref/rest/v3/purchases.products
     */
    private function googleDogrula(string $fisVerisi, string $urunKodu, string $urunTipi): array
    {
        $ayarlar = $this->mobilOdemeAyarServisi->googlePlayAyarlari();
        $paketAdi = $ayarlar['paket_adi'] ?? null;

        if (!$this->mobilOdemeAyarServisi->googlePlayHazirMi() || !$paketAdi) {
            return [
                'gecerli' => false,
                'islem_kodu' => null,
                'hata' => 'Google Play odeme ayarlari panelde eksik.',
            ];
        }

        try {
            $erisimJetonu = $this->googleErisimJetonuAl($ayarlar);

            $url = $urunTipi === 'abonelik'
                ? "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$paketAdi}/purchases/subscriptionsv2/tokens/{$fisVerisi}"
                : "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$paketAdi}/purchases/products/{$urunKodu}/tokens/{$fisVerisi}";

            $yanit = Http::withToken($erisimJetonu)
                ->timeout(15)
                ->get($url);

            if ($yanit->failed()) {
                Log::warning('Google Play fiş doğrulama başarısız', [
                    'durum' => $yanit->status(),
                    'yanit' => $yanit->body(),
                ]);

                return [
                    'gecerli' => false,
                    'islem_kodu' => null,
                    'hata' => 'Google doğrulama başarısız: HTTP ' . $yanit->status(),
                ];
            }

            $veri = $yanit->json();

            if ($urunTipi === 'abonelik') {
                return $this->googleAbonelikSonucunuDonustur($veri, $urunKodu, $fisVerisi);
            }

            // purchaseState: 0 = satın alındı, 1 = iptal, 2 = beklemede
            if (($veri['purchaseState'] ?? -1) !== 0) {
                return [
                    'gecerli' => false,
                    'islem_kodu' => $veri['orderId'] ?? null,
                    'hata' => 'Satın alma tamamlanmamış.',
                ];
            }

            // consumptionState: 0 = henüz tüketilmedi (bizim işleyebileceğimiz)
            return [
                'gecerli' => true,
                'islem_kodu' => $veri['orderId'] ?? $fisVerisi,
                'hata' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Google Play fiş doğrulama hatası', ['hata' => $e->getMessage()]);

            return [
                'gecerli' => false,
                'islem_kodu' => null,
                'hata' => 'Google sunucusuna bağlanılamadı.',
            ];
        }
    }

    /**
     * Apple App Store Server API için JWT oluşturur.
     * Gerekli config: services.apple.issuer_id, key_id, private_key_path
     */
    private function appleJwtOlustur(array $ayarlar): string
    {
        $issuerId = $ayarlar['issuer_id'];
        $keyId = $ayarlar['key_id'];
        $ozelAnahtarYolu = $ayarlar['private_key_path'];

        $ozelAnahtar = file_get_contents($ozelAnahtarYolu);

        $baslik = [
            'alg' => 'ES256',
            'kid' => $keyId,
            'typ' => 'JWT',
        ];

        $yukBilgisi = [
            'iss' => $issuerId,
            'iat' => time(),
            'exp' => time() + 3600,
            'aud' => 'appstoreconnect-v1',
            'bid' => $ayarlar['bundle_id'],
        ];

        $baslikB64 = rtrim(strtr(base64_encode(json_encode($baslik)), '+/', '-_'), '=');
        $yukB64 = rtrim(strtr(base64_encode(json_encode($yukBilgisi)), '+/', '-_'), '=');

        $imzalanacak = "{$baslikB64}.{$yukB64}";
        $imza = '';
        openssl_sign($imzalanacak, $imza, $ozelAnahtar, OPENSSL_ALGO_SHA256);

        // DER → raw R|S (64 byte)
        $imzaRaw = $this->derDenRawIcin($imza);
        $imzaB64 = rtrim(strtr(base64_encode($imzaRaw), '+/', '-_'), '=');

        return "{$baslikB64}.{$yukB64}.{$imzaB64}";
    }

    /**
     * Google OAuth2 service account ile erişim jetonu alır.
     */
    private function googleErisimJetonuAl(array $ayarlar): string
    {
        $servisHesabi = $ayarlar['service_account_path'];
        $kimlikBilgisi = json_decode(file_get_contents($servisHesabi), true);

        $baslik = ['alg' => 'RS256', 'typ' => 'JWT'];
        $yukBilgisi = [
            'iss' => $kimlikBilgisi['client_email'],
            'scope' => 'https://www.googleapis.com/auth/androidpublisher',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $baslikB64 = rtrim(strtr(base64_encode(json_encode($baslik)), '+/', '-_'), '=');
        $yukB64 = rtrim(strtr(base64_encode(json_encode($yukBilgisi)), '+/', '-_'), '=');

        $imzalanacak = "{$baslikB64}.{$yukB64}";
        openssl_sign($imzalanacak, $imza, $kimlikBilgisi['private_key'], OPENSSL_ALGO_SHA256);
        $imzaB64 = rtrim(strtr(base64_encode($imza), '+/', '-_'), '=');

        $jwt = "{$baslikB64}.{$yukB64}.{$imzaB64}";

        $yanit = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        return (string) $yanit->json('access_token');
    }

    /**
     * @param  array<string, mixed>  $veri
     * @return array{gecerli: bool, islem_kodu: ?string, hata: ?string}
     */
    private function googleAbonelikSonucunuDonustur(array $veri, string $urunKodu, string $fisVerisi): array
    {
        $lineItems = $veri['lineItems'] ?? [];
        $ilkSatir = is_array($lineItems) ? ($lineItems[0] ?? null) : null;
        $lineItem = is_array($ilkSatir) ? $ilkSatir : [];
        $productId = $lineItem['productId'] ?? null;
        $state = $veri['subscriptionState'] ?? null;
        $islemKodu = $veri['latestOrderId'] ?? $fisVerisi;

        if (is_string($productId) && $productId !== '' && $productId !== $urunKodu) {
            return [
                'gecerli' => false,
                'islem_kodu' => (string) $islemKodu,
                'hata' => 'Abonelik urun kodu uyusmuyor.',
            ];
        }

        if (!in_array($state, ['SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD'], true)) {
            return [
                'gecerli' => false,
                'islem_kodu' => (string) $islemKodu,
                'hata' => 'Abonelik henuz aktif degil.',
            ];
        }

        return [
            'gecerli' => true,
            'islem_kodu' => (string) $islemKodu,
            'hata' => null,
        ];
    }

    /**
     * DER encoded ECDSA imzasını raw R|S formatına çevirir (64 byte).
     */
    private function derDenRawIcin(string $der): string
    {
        $hex = bin2hex($der);
        // DER: 30 <len> 02 <rlen> <R> 02 <slen> <S>
        $pos = 4; // 30 xx atla
        $rLen = hexdec(substr($hex, $pos + 2, 2)) * 2;
        $r = substr($hex, $pos + 4, $rLen);
        $pos += 4 + $rLen;
        $sLen = hexdec(substr($hex, $pos + 2, 2)) * 2;
        $s = substr($hex, $pos + 4, $sLen);

        // 32 byte'a pad
        $r = str_pad(substr($r, -64), 64, '0', STR_PAD_LEFT);
        $s = str_pad(substr($s, -64), 64, '0', STR_PAD_LEFT);

        return hex2bin($r . $s);
    }
}
