<?php

namespace App\Services\Kimlik\Sosyal;

use App\Models\User;
use App\Services\Kimlik\AuthPuanServisi;
use App\Services\Kimlik\IstemciYetenekServisi;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SosyalAuthServisi
{
    public function __construct(
        private SosyalOturumServisi $sosyalOturumServisi,
        private IstemciYetenekServisi $istemciYetenekServisi,
        private AuthPuanServisi $authPuanServisi,
    ) {}

    public function giris(array $veri): array
    {
        $kimlik = $this->saglayici($veri['provider'])->dogrula($veri);
        $user = User::where($kimlik->providerKolonu(), $kimlik->providerUserId)->first();

        if (!$user && $kimlik->email && $kimlik->emailVerified) {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($kimlik->email)])->first();
        }

        if ($user) {
            $this->hesapAktifMiKontrolEt($user);
            $this->kimlikBilgisiniBagla($user, $kimlik);
            $this->authPuanServisi->gunlukGirisBonusuUygula($user);

            $token = $user->createToken(
                $veri['istemci_tipi'],
                $this->istemciYetenekServisi->belirle($veri['istemci_tipi']),
            );

            return [
                'durum' => 'authenticated',
                'user' => $user->fresh()->load('fotograflar', 'aiAyar'),
                'token' => $token->plainTextToken,
            ];
        }

        $socialSession = $this->sosyalOturumServisi->olustur($kimlik, $veri['istemci_tipi']);

        return [
            'durum' => 'onboarding_required',
            'social_session' => $socialSession,
            'prefill' => [
                'provider' => $kimlik->provider,
                'ad' => $kimlik->displayName,
                'email' => $kimlik->email,
                'avatar_url' => $kimlik->avatarUrl,
            ],
        ];
    }

    public function kayit(array $veri, ?UploadedFile $dosya = null): array
    {
        $oturum = $this->sosyalOturumServisi->coz($veri['social_session']);
        $providerKolonu = $this->providerKolonu($oturum['provider']);
        $providerUserId = (string) $oturum['provider_user_id'];
        $istemciTipi = (string) $oturum['istemci_tipi'];

        $user = User::where($providerKolonu, $providerUserId)->first();

        if (!$user && !empty($oturum['email']) && !empty($oturum['email_verified'])) {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower((string) $oturum['email'])])->first();
        }

        if ($user) {
            $this->hesapAktifMiKontrolEt($user);
            $this->kimlikBilgisiniBagla(
                $user,
                new SosyalKimlikBilgisi(
                    provider: (string) $oturum['provider'],
                    providerUserId: $providerUserId,
                    email: $oturum['email'] ?? null,
                    emailVerified: (bool) ($oturum['email_verified'] ?? false),
                    displayName: $oturum['display_name'] ?? null,
                    givenName: $oturum['given_name'] ?? null,
                    familyName: $oturum['family_name'] ?? null,
                    avatarUrl: $oturum['avatar_url'] ?? null,
                ),
            );
            $this->authPuanServisi->gunlukGirisBonusuUygula($user);

            $token = $user->createToken(
                $istemciTipi,
                $this->istemciYetenekServisi->belirle($istemciTipi),
            );

            return [
                'durum' => 'authenticated',
                'user' => $user->fresh()->load('fotograflar', 'aiAyar'),
                'token' => $token->plainTextToken,
                'status' => 200,
            ];
        }

        $kullaniciAdi = $this->normalizeUsername($veri['kullanici_adi']);

        if (!$this->kullaniciAdiMusait($kullaniciAdi)) {
            throw ValidationException::withMessages([
                'kullanici_adi' => ['Bu kullanıcı adı zaten kullanılıyor.'],
            ]);
        }

        $user = User::create([
            'ad' => trim((string) $veri['ad']),
            'kullanici_adi' => $kullaniciAdi,
            'email' => $oturum['email'] ?? null,
            'email_verified_at' => !empty($oturum['email']) && !empty($oturum['email_verified']) ? now() : null,
            $providerKolonu => $providerUserId,
            'hesap_tipi' => 'user',
            'hesap_durumu' => 'aktif',
            'cinsiyet' => $veri['cinsiyet'],
            'dogum_yili' => $veri['dogum_yili'],
            'ulke' => $veri['ulke'] ?? null,
            'il' => $veri['il'] ?? null,
        ]);

        $this->authPuanServisi->kayitBonusuUygula($user);

        if ($dosya !== null) {
            $yol = $dosya->store('fotograflar/' . $user->id, 'public');

            $user->fotograflar()->create([
                'dosya_yolu' => $yol,
                'sira_no' => 0,
                'ana_fotograf_mi' => true,
            ]);

            $user->forceFill([
                'profil_resmi' => $yol,
            ])->save();
        }

        $token = $user->createToken(
            $istemciTipi,
            $this->istemciYetenekServisi->belirle($istemciTipi),
        );

        return [
            'durum' => 'authenticated',
            'user' => $user->fresh()->load('fotograflar', 'aiAyar'),
            'token' => $token->plainTextToken,
            'status' => 201,
        ];
    }

    public function kullaniciAdiMusait(string $kullaniciAdi): bool
    {
        $normalize = $this->normalizeUsername($kullaniciAdi);

        return !User::whereRaw('LOWER(kullanici_adi) = ?', [mb_strtolower($normalize)])->exists();
    }

    public function normalizeUsername(string $kullaniciAdi): string
    {
        return ltrim(trim($kullaniciAdi), '@');
    }

    private function hesapAktifMiKontrolEt(User $user): void
    {
        if ($user->hesap_durumu !== 'aktif') {
            throw ValidationException::withMessages([
                'provider' => ['Hesap aktif değil.'],
            ]);
        }
    }

    private function kimlikBilgisiniBagla(User $user, SosyalKimlikBilgisi $kimlik): void
    {
        $guncellenecekAlanlar = [
            $kimlik->providerKolonu() => $kimlik->providerUserId,
        ];

        if ($kimlik->email && $kimlik->emailVerified && !$user->email) {
            $guncellenecekAlanlar['email'] = $kimlik->email;
            $guncellenecekAlanlar['email_verified_at'] = now();
        }

        if ($kimlik->avatarUrl && !$user->profil_resmi) {
            $guncellenecekAlanlar['profil_resmi'] = $kimlik->avatarUrl;
        }

        $user->forceFill($guncellenecekAlanlar)->save();
    }

    private function providerKolonu(string $provider): string
    {
        return match ($provider) {
            'apple' => 'apple_kimlik',
            'google' => 'google_kimlik',
        };
    }

    private function saglayici(string $provider): SosyalKimlikSaglayici
    {
        $abstract = match ($provider) {
            'apple' => 'social.provider.apple',
            'google' => 'social.provider.google',
        };

        $saglayici = app($abstract);

        if (!$saglayici instanceof SosyalKimlikSaglayici) {
            throw ValidationException::withMessages([
                'provider' => ['Sosyal kimlik sağlayıcısı yapılandırılamadı.'],
            ]);
        }

        return $saglayici;
    }
}
