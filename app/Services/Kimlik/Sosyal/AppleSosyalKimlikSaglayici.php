<?php

namespace App\Services\Kimlik\Sosyal;

use App\Services\AyarServisi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

class AppleSosyalKimlikSaglayici implements SosyalKimlikSaglayici
{
    public function __construct(private AyarServisi $ayarServisi) {}

    public function dogrula(array $veri): SosyalKimlikBilgisi
    {
        $clientId = $this->ayarDegeri('apple_bundle_id', 'services.apple.bundle_id');
        $issuerId = $this->ayarDegeri('apple_issuer_id', 'services.apple.issuer_id');
        $keyId = $this->ayarDegeri('apple_key_id', 'services.apple.key_id');
        $privateKeyPath = $this->privateKeyYolu();
        $this->ayarServisi->al('apple_sandbox', config('services.apple.sandbox', true));

        $response = Http::asForm()->post('https://appleid.apple.com/auth/token', [
            'grant_type' => 'authorization_code',
            'code' => $veri['token'],
            'client_id' => $clientId,
            'client_secret' => $this->clientSecretOlustur(
                clientId: $clientId,
                issuerId: $issuerId,
                keyId: $keyId,
                privateKeyPath: $privateKeyPath,
            ),
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'token' => ['Apple kimliği doğrulanamadı.'],
            ]);
        }

        $payload = $this->jwtPayloadCoz((string) $response->json('id_token'));
        $providerUserId = (string) ($payload['sub'] ?? '');

        if ($providerUserId === '') {
            throw ValidationException::withMessages([
                'token' => ['Apple kullanıcı kimliği alınamadı.'],
            ]);
        }

        if (($payload['iss'] ?? null) !== 'https://appleid.apple.com') {
            throw ValidationException::withMessages([
                'token' => ['Apple sağlayıcısı doğrulanamadı.'],
            ]);
        }

        if (($payload['aud'] ?? null) !== $clientId) {
            throw ValidationException::withMessages([
                'token' => ['Apple uygulama kimliği eşleşmedi.'],
            ]);
        }

        if ((int) ($payload['exp'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'token' => ['Apple oturumu süresi doldu.'],
            ]);
        }

        $ad = $veri['ad'] ?? null;
        $soyad = $veri['soyad'] ?? null;
        $tamAd = trim(implode(' ', array_filter([$ad, $soyad])));

        return new SosyalKimlikBilgisi(
            provider: 'apple',
            providerUserId: $providerUserId,
            email: $payload['email'] ?? null,
            emailVerified: filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            displayName: $tamAd !== '' ? $tamAd : null,
            givenName: $ad,
            familyName: $soyad,
            avatarUrl: $veri['avatar_url'] ?? null,
        );
    }

    private function ayarDegeri(string $anahtar, string $configYolu): string
    {
        $deger = $this->ayarServisi->al($anahtar, config($configYolu));

        if (!is_string($deger) || trim($deger) === '') {
            throw ValidationException::withMessages([
                'provider' => ['Apple giriş ayarları eksik.'],
            ]);
        }

        return trim($deger);
    }

    private function privateKeyYolu(): string
    {
        $yol = $this->ayarDegeri('apple_private_key_path', 'services.apple.private_key_path');

        if (is_file($yol)) {
            return $yol;
        }

        $storageYolu = Storage::disk('local')->path($yol);

        if (is_file($storageYolu)) {
            return $storageYolu;
        }

        throw ValidationException::withMessages([
            'provider' => ['Apple private key dosyası bulunamadı.'],
        ]);
    }

    private function clientSecretOlustur(
        string $clientId,
        string $issuerId,
        string $keyId,
        string $privateKeyPath,
    ): string {
        try {
            $header = $this->base64UrlEncode(json_encode([
                'alg' => 'ES256',
                'kid' => $keyId,
                'typ' => 'JWT',
            ], JSON_THROW_ON_ERROR));

            $payload = $this->base64UrlEncode(json_encode([
                'iss' => $issuerId,
                'iat' => now()->timestamp,
                'exp' => now()->addMinutes(5)->timestamp,
                'aud' => 'https://appleid.apple.com',
                'sub' => $clientId,
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw new RuntimeException('Apple client secret üretilemedi.');
        }

        $signingInput = $header . '.' . $payload;
        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath) ?: '');

        if ($privateKey === false) {
            throw ValidationException::withMessages([
                'provider' => ['Apple private key okunamadı.'],
            ]);
        }

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);

        if (!$signed) {
            throw ValidationException::withMessages([
                'provider' => ['Apple client secret imzalanamadı.'],
            ]);
        }

        return $signingInput . '.' . $this->base64UrlEncode($this->derImzaRawImzayaCevir($signature, 64));
    }

    private function jwtPayloadCoz(string $jwt): array
    {
        try {
            $parcalar = explode('.', $jwt);

            if (count($parcalar) !== 3) {
                throw new RuntimeException('Geçersiz Apple JWT yapısı.');
            }

            $payload = $this->base64UrlDecode($parcalar[1]);

            return json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'token' => ['Apple kimlik verisi çözülemedi.'],
            ]);
        }
    }

    private function derImzaRawImzayaCevir(string $signature, int $uzunluk): string
    {
        $offset = 0;

        if (ord($signature[$offset]) !== 0x30) {
            throw new RuntimeException('Geçersiz DER imzası.');
        }

        $offset++;
        $this->derUzunlukOku($signature, $offset);

        if (ord($signature[$offset]) !== 0x02) {
            throw new RuntimeException('Geçersiz DER r bileşeni.');
        }

        $offset++;
        $rUzunluk = $this->derUzunlukOku($signature, $offset);
        $r = substr($signature, $offset, $rUzunluk);
        $offset += $rUzunluk;

        if (ord($signature[$offset]) !== 0x02) {
            throw new RuntimeException('Geçersiz DER s bileşeni.');
        }

        $offset++;
        $sUzunluk = $this->derUzunlukOku($signature, $offset);
        $s = substr($signature, $offset, $sUzunluk);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $parcaUzunluk = (int) ($uzunluk / 2);

        return str_pad($r, $parcaUzunluk, "\x00", STR_PAD_LEFT)
            . str_pad($s, $parcaUzunluk, "\x00", STR_PAD_LEFT);
    }

    private function derUzunlukOku(string $veri, int &$offset): int
    {
        $uzunluk = ord($veri[$offset]);
        $offset++;

        if (($uzunluk & 0x80) === 0) {
            return $uzunluk;
        }

        $byteSayisi = $uzunluk & 0x7f;
        $uzunluk = 0;

        for ($i = 0; $i < $byteSayisi; $i++) {
            $uzunluk = ($uzunluk << 8) | ord($veri[$offset]);
            $offset++;
        }

        return $uzunluk;
    }

    private function base64UrlEncode(string $deger): string
    {
        return rtrim(strtr(base64_encode($deger), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $deger): string
    {
        $padding = strlen($deger) % 4;

        if ($padding > 0) {
            $deger .= str_repeat('=', 4 - $padding);
        }

        return (string) base64_decode(strtr($deger, '-_', '+/'), true);
    }
}
