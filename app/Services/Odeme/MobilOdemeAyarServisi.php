<?php

namespace App\Services\Odeme;

use App\Services\AyarServisi;
use Illuminate\Support\Facades\Storage;

class MobilOdemeAyarServisi
{
    public function __construct(private AyarServisi $ayarServisi) {}

    public function kanalAktifMi(string $platform): bool
    {
        return match ($platform) {
            'ios' => (bool) $this->ayarServisi->al('apple_odeme_aktif_mi', true),
            'android' => (bool) $this->ayarServisi->al('google_play_odeme_aktif_mi', true),
            default => false,
        };
    }

    public function platformDurumu(string $platform): array
    {
        return [
            'aktif' => $this->kanalAktifMi($platform),
            'hazir' => match ($platform) {
                'ios' => $this->appleHazirMi(),
                'android' => $this->googlePlayHazirMi(),
                default => false,
            },
        ];
    }

    public function kanalKullanilabilirMi(string $platform): bool
    {
        $durum = $this->platformDurumu($platform);

        return $durum['aktif'] && $durum['hazir'];
    }

    public function appleAyarlari(): array
    {
        return [
            'issuer_id' => $this->stringDeger('apple_issuer_id', 'services.apple.issuer_id'),
            'key_id' => $this->stringDeger('apple_key_id', 'services.apple.key_id'),
            'bundle_id' => $this->stringDeger('apple_bundle_id', 'services.apple.bundle_id'),
            'sandbox' => (bool) $this->ayarServisi->al('apple_sandbox', config('services.apple.sandbox', true)),
            'private_key_path' => $this->dosyaYolu('apple_private_key_path', 'services.apple.private_key_path'),
        ];
    }

    public function googlePlayAyarlari(): array
    {
        return [
            'paket_adi' => $this->stringDeger('google_play_paket_adi', 'services.google_play.paket_adi'),
            'service_account_path' => $this->dosyaYolu('google_play_service_account_path', 'services.google_play.service_account_path'),
        ];
    }

    public function appleHazirMi(): bool
    {
        $ayarlar = $this->appleAyarlari();

        return filled($ayarlar['issuer_id'])
            && filled($ayarlar['key_id'])
            && filled($ayarlar['bundle_id'])
            && filled($ayarlar['private_key_path']);
    }

    public function googlePlayHazirMi(): bool
    {
        $ayarlar = $this->googlePlayAyarlari();

        return filled($ayarlar['paket_adi'])
            && filled($ayarlar['service_account_path']);
    }

    private function stringDeger(string $anahtar, string $configYolu): ?string
    {
        $deger = $this->ayarServisi->al($anahtar, config($configYolu));

        if (!is_string($deger)) {
            return null;
        }

        $normalized = trim($deger);

        return $normalized === '' ? null : $normalized;
    }

    private function dosyaYolu(string $anahtar, string $configYolu): ?string
    {
        $yol = $this->stringDeger($anahtar, $configYolu);

        if ($yol === null) {
            return null;
        }

        if (is_file($yol)) {
            return $yol;
        }

        $localYol = Storage::disk('local')->path($yol);

        if (is_file($localYol)) {
            return $localYol;
        }

        return null;
    }
}
