<?php

namespace App\Services\Kimlik\Sosyal;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Throwable;

class SosyalOturumServisi
{
    private const SURE_DAKIKA = 15;

    public function olustur(SosyalKimlikBilgisi $kimlik, string $istemciTipi): string
    {
        $payload = [
            'provider' => $kimlik->provider,
            'provider_user_id' => $kimlik->providerUserId,
            'email' => $kimlik->email,
            'email_verified' => $kimlik->emailVerified,
            'display_name' => $kimlik->displayName,
            'given_name' => $kimlik->givenName,
            'family_name' => $kimlik->familyName,
            'avatar_url' => $kimlik->avatarUrl,
            'istemci_tipi' => $istemciTipi,
            'exp' => now()->addMinutes(self::SURE_DAKIKA)->timestamp,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function coz(string $socialSession): array
    {
        try {
            $json = Crypt::decryptString($socialSession);
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'social_session' => ['Sosyal oturum doğrulanamadı.'],
            ]);
        }

        if (($payload['exp'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'social_session' => ['Sosyal oturum süresi doldu.'],
            ]);
        }

        if (!isset($payload['provider'], $payload['provider_user_id'], $payload['istemci_tipi'])) {
            throw ValidationException::withMessages([
                'social_session' => ['Sosyal oturum verisi eksik.'],
            ]);
        }

        return $payload;
    }
}
