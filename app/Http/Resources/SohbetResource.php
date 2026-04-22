<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SohbetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eslesme_id' => $this->eslesme_id,
            'eslesme' => new EslesmeResource($this->whenLoaded('eslesme')),
            'son_mesaj' => new MesajResource($this->whenLoaded('sonMesaj')),
            'son_mesaj_tarihi' => $this->son_mesaj_tarihi,
            'toplam_mesaj_sayisi' => $this->toplam_mesaj_sayisi,
            'okunmamis_sayisi' => $this->okunmamis_sayisi,
            'durum' => $this->durum,
            'created_at' => $this->created_at,
        ];
    }
}
