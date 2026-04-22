<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MesajResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sohbet_id' => $this->sohbet_id,
            'gonderen' => new KullaniciOzetResource($this->whenLoaded('gonderen')),
            'mesaj_tipi' => $this->mesaj_tipi,
            'mesaj_metni' => $this->mesaj_metni,
            'dosya_yolu' => MediaUrl::resolve($this->dosya_yolu),
            'dosya_suresi' => $this->dosya_suresi,
            'okundu_mu' => $this->okundu_mu,
            'ai_tarafindan_uretildi_mi' => $this->ai_tarafindan_uretildi_mi,
            'cevaplanan_mesaj_id' => $this->cevaplanan_mesaj_id,
            'created_at' => $this->created_at,
        ];
    }
}
