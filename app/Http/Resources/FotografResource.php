<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FotografResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dosya_yolu' => MediaUrl::resolve($this->dosya_yolu),
            'onizleme_yolu' => MediaUrl::resolve($this->onizleme_yolu),
            'medya_tipi' => $this->medya_tipi,
            'mime_tipi' => $this->mime_tipi,
            'sure_saniye' => $this->sure_saniye,
            'sira_no' => $this->sira_no,
            'ana_fotograf_mi' => $this->ana_fotograf_mi,
            'aktif_mi' => $this->aktif_mi,
            'created_at' => $this->created_at,
        ];
    }
}
