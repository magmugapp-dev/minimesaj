<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KullaniciOzetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ad' => $this->ad,
            'kullanici_adi' => $this->kullanici_adi,
            'profil_resmi' => MediaUrl::resolve($this->profil_resmi),
            'cevrim_ici_mi' => $this->cevrim_ici_mi,
        ];
    }
}
