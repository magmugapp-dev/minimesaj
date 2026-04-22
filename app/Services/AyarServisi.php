<?php

namespace App\Services;

use App\Models\Ayar;
use Illuminate\Support\Facades\Cache;

class AyarServisi
{
    private const CACHE_PREFIX = 'ayar:';
    private const CACHE_TTL = 3600; // 1 saat

    public function al(string $anahtar, mixed $varsayilan = null): mixed
    {
        return Cache::remember(self::CACHE_PREFIX . $anahtar, self::CACHE_TTL, function () use ($anahtar, $varsayilan) {
            $ayar = Ayar::where('anahtar', $anahtar)->first();

            if (!$ayar) {
                return $varsayilan ?? env($anahtar);
            }

            return $this->degerDonustur($ayar->deger, $ayar->tip);
        });
    }

    public function ayarla(string $anahtar, mixed $deger): void
    {
        $ayar = Ayar::where('anahtar', $anahtar)->first();

        if ($ayar) {
            $ayar->update(['deger' => $this->degerSerialize($deger, $ayar->tip)]);
        }

        Cache::forget(self::CACHE_PREFIX . $anahtar);
    }

    public function grupGetir(string $grup): array
    {
        $ayarlar = Ayar::where('grup', $grup)->orderBy('id')->get();

        return $ayarlar->map(function (Ayar $ayar) {
            return [
                'anahtar' => $ayar->anahtar,
                'deger' => $this->degerDonustur($ayar->deger, $ayar->tip),
                'tip' => $ayar->tip,
                'aciklama' => $ayar->aciklama,
            ];
        })->keyBy('anahtar')->toArray();
    }

    public function topluGuncelle(array $ayarlar): void
    {
        foreach ($ayarlar as $anahtar => $deger) {
            $ayar = Ayar::where('anahtar', $anahtar)->first();

            if ($ayar) {
                $kaydedilecek = $this->degerSerialize($deger, $ayar->tip);
                $ayar->update(['deger' => $kaydedilecek]);
                Cache::forget(self::CACHE_PREFIX . $anahtar);
            }
        }
    }

    public function onbellekTemizle(): void
    {
        $anahtarlar = Ayar::pluck('anahtar');

        foreach ($anahtarlar as $anahtar) {
            Cache::forget(self::CACHE_PREFIX . $anahtar);
        }
    }

    private function degerDonustur(mixed $deger, string $tip): mixed
    {
        if ($deger === null) {
            return null;
        }

        return match ($tip) {
            'integer' => (int) $deger,
            'boolean' => filter_var($deger, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($deger, true),
            default => $deger,
        };
    }

    private function degerSerialize(mixed $deger, string $tip): ?string
    {
        if ($deger === null) {
            return null;
        }

        return match ($tip) {
            'boolean' => $deger ? '1' : '0',
            'json' => is_string($deger) ? $deger : json_encode($deger),
            default => (string) $deger,
        };
    }
}
