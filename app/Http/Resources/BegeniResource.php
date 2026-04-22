<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BegeniResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'begenen' => new KullaniciOzetResource($this->whenLoaded('begenen')),
            'begenilen' => new KullaniciOzetResource($this->whenLoaded('begenilen')),
            'eslesmeye_donustu_mu' => $this->eslesmeye_donustu_mu,
            'goruldu_mu' => $this->goruldu_mu,
            'created_at' => $this->created_at,
        ];
    }
}
