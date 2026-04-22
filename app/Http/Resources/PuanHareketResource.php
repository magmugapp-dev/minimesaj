<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PuanHareketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'islem_tipi' => $this->islem_tipi,
            'puan_miktari' => $this->puan_miktari,
            'onceki_bakiye' => $this->onceki_bakiye,
            'sonraki_bakiye' => $this->sonraki_bakiye,
            'aciklama' => $this->aciklama,
            'referans_tipi' => $this->referans_tipi,
            'referans_id' => $this->referans_id,
            'created_at' => $this->created_at,
        ];
    }
}
