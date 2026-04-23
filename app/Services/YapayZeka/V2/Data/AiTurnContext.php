<?php

namespace App\Services\YapayZeka\V2\Data;

use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use App\Models\InstagramMesaj;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;

final class AiTurnContext
{
    public function __construct(
        public readonly string $kanal,
        public readonly string $turnType,
        public readonly User $aiUser,
        public readonly ?Sohbet $sohbet = null,
        public readonly ?Mesaj $gelenMesaj = null,
        public readonly ?User $hedefUser = null,
        public readonly ?InstagramHesap $instagramHesap = null,
        public readonly ?InstagramKisi $instagramKisi = null,
        public readonly ?InstagramMesaj $instagramMesaj = null,
    ) {}

    public function hedefTipi(): string
    {
        return $this->kanal === 'instagram' ? 'instagram_kisi' : 'user';
    }

    public function hedefId(): int
    {
        return $this->kanal === 'instagram'
            ? (int) ($this->instagramKisi?->id ?? 0)
            : (int) ($this->hedefUser?->id ?? 0);
    }

    public function referansMetni(): string
    {
        if ($this->kanal === 'instagram') {
            return trim((string) ($this->instagramMesaj?->mesaj_metni ?? ''));
        }

        return trim((string) ($this->gelenMesaj?->mesaj_metni ?? ''));
    }

    public function hedefGorunenAdi(): string
    {
        if ($this->kanal === 'instagram') {
            return trim((string) ($this->instagramKisi?->gorunen_ad ?: $this->instagramKisi?->kullanici_adi ?: 'Kullanici'));
        }

        return trim((string) ($this->hedefUser?->ad ?: 'Kullanici'));
    }
}
