<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstagramMesajResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instagram_hesap_id' => $this->instagram_hesap_id,
            'instagram_kisi_id' => $this->instagram_kisi_id,
            'kisi' => new InstagramKisiResource($this->whenLoaded('kisi')),
            'gonderen_tipi' => $this->gonderen_tipi,
            'mesaj_metni' => $this->mesaj_metni,
            'mesaj_tipi' => $this->mesaj_tipi,
            'ai_cevapladi_mi' => $this->ai_cevapladi_mi,
            'gonderildi_mi' => $this->gonderildi_mi,
            'instagram_mesaj_kodu' => $this->instagram_mesaj_kodu,
            'created_at' => $this->created_at,
        ];
    }
}
