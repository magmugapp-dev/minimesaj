<?php

namespace App\Services\YapayZeka\V2\Data;

final class AiConversationStateSnapshot
{
    public function __construct(
        public readonly int $samimiyetPuani,
        public readonly int $ilgiPuani,
        public readonly int $guvenPuani,
        public readonly int $enerjiPuani,
        public readonly string $ruhHali,
        public readonly int $gerilimSeviyesi,
        public readonly ?string $sonKonu,
        public readonly ?string $sonKullaniciDuygusu,
        public readonly ?string $sonAiNiyeti,
        public readonly ?string $sonOzet,
        public readonly string $aiDurumu,
    ) {}

    public function toArray(): array
    {
        return [
            'samimiyet_puani' => $this->samimiyetPuani,
            'ilgi_puani' => $this->ilgiPuani,
            'guven_puani' => $this->guvenPuani,
            'enerji_puani' => $this->enerjiPuani,
            'ruh_hali' => $this->ruhHali,
            'gerilim_seviyesi' => $this->gerilimSeviyesi,
            'son_konu' => $this->sonKonu,
            'son_kullanici_duygusu' => $this->sonKullaniciDuygusu,
            'son_ai_niyeti' => $this->sonAiNiyeti,
            'son_ozet' => $this->sonOzet,
            'ai_durumu' => $this->aiDurumu,
        ];
    }
}
