<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SikayetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sikayet_eden' => new KullaniciOzetResource($this->whenLoaded('sikayetEden')),
            'hedef_tipi' => $this->hedef_tipi,
            'hedef_id' => $this->hedef_id,
            'kategori' => $this->kategori,
            'aciklama' => $this->aciklama,
            'durum' => $this->durum,
            'yonetici_notu' => $this->yonetici_notu,
            'created_at' => $this->created_at,
        ];
    }
}
