<?php

namespace App\Services\Kimlik\Sosyal;

use App\Services\AyarServisi;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleSosyalKimlikSaglayici implements SosyalKimlikSaglayici
{
    public function __construct(private AyarServisi $ayarServisi) {}

    public function dogrula(array $veri): SosyalKimlikBilgisi
    {
        $izinliClientIdler = $this->izinliClientIdleri();

        if ($izinliClientIdler === []) {
            throw ValidationException::withMessages([
                'provider' => ['Google giriş ayarları eksik.'],
            ]);
        }

        $response = Http::asForm()->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $veri['token'],
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'token' => ['Google kimliği doğrulanamadı.'],
            ]);
        }

        $payload = $response->json();
        $audience = (string) ($payload['aud'] ?? '');
        $issuer = (string) ($payload['iss'] ?? '');
        $expiresAt = (int) ($payload['exp'] ?? 0);

        if (!in_array($audience, $izinliClientIdler, true)) {
            throw ValidationException::withMessages([
                'token' => ['Google istemci kimliği eşleşmedi.'],
            ]);
        }

        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw ValidationException::withMessages([
                'token' => ['Google sağlayıcısı doğrulanamadı.'],
            ]);
        }

        if ($expiresAt !== 0 && $expiresAt < now()->timestamp) {
            throw ValidationException::withMessages([
                'token' => ['Google oturumu süresi doldu.'],
            ]);
        }

        return new SosyalKimlikBilgisi(
            provider: 'google',
            providerUserId: (string) ($payload['sub'] ?? ''),
            email: $payload['email'] ?? null,
            emailVerified: filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            displayName: $payload['name'] ?? $this->adSoyadBirlestir($payload['given_name'] ?? null, $payload['family_name'] ?? null),
            givenName: $payload['given_name'] ?? null,
            familyName: $payload['family_name'] ?? null,
            avatarUrl: $payload['picture'] ?? null,
        );
    }

    private function adSoyadBirlestir(?string $ad, ?string $soyad): ?string
    {
        $tamAd = trim(implode(' ', array_filter([$ad, $soyad])));

        return $tamAd !== '' ? $tamAd : null;
    }

    private function izinliClientIdleri(): array
    {
        $ayarlar = [
            $this->ayarServisi->al('google_auth_ios_client_id', config('services.google_auth.ios_client_id')),
            $this->ayarServisi->al('google_auth_android_client_id', config('services.google_auth.android_client_id')),
            $this->ayarServisi->al('google_auth_server_client_id', config('services.google_auth.server_client_id')),
        ];

        return array_values(array_unique(array_filter(array_map(function ($deger) {
            if (!is_string($deger)) {
                return null;
            }

            $trimlenmis = trim($deger);

            return $trimlenmis !== '' ? $trimlenmis : null;
        }, $ayarlar))));
    }
}
