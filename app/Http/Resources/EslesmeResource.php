<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EslesmeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new KullaniciOzetResource($this->whenLoaded('user')),
            'eslesen_user' => new KullaniciOzetResource($this->whenLoaded('eslesenUser')),
            'eslesme_turu' => $this->eslesme_turu,
            'eslesme_kaynagi' => $this->eslesme_kaynagi,
            'durum' => $this->durum,
            'sohbet' => new SohbetResource($this->whenLoaded('sohbet')),
            'created_at' => $this->created_at,
        ];
    }
}
