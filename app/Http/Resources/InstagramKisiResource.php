<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstagramKisiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instagram_kisi_id' => $this->instagram_kisi_id,
            'kullanici_adi' => $this->kullanici_adi,
            'gorunen_ad' => $this->gorunen_ad,
            'profil_resmi' => $this->profil_resmi,
            'notlar' => $this->notlar,
            'son_mesaj_tarihi' => $this->son_mesaj_tarihi,
            'created_at' => $this->created_at,
        ];
    }
}
