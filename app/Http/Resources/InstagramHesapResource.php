<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstagramHesapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instagram_kullanici_adi' => $this->instagram_kullanici_adi,
            'instagram_profil_id' => $this->instagram_profil_id,
            'otomatik_cevap_aktif_mi' => $this->otomatik_cevap_aktif_mi,
            'yarim_otomatik_mod_aktif_mi' => $this->yarim_otomatik_mod_aktif_mi,
            'aktif_mi' => $this->aktif_mi,
            'son_baglanti_tarihi' => $this->son_baglanti_tarihi,
            'created_at' => $this->created_at,
        ];
    }
}
