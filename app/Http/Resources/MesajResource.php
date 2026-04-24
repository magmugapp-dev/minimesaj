<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use App\Support\Language;
use App\Support\AiMessageTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MesajResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $text = $this->mesaj_metni;
        if ($this->ai_tarafindan_uretildi_mi) {
            $text = AiMessageTextSanitizer::sanitize($text);
        }

        return [
            'id' => $this->id,
            'sohbet_id' => $this->sohbet_id,
            'gonderen' => new KullaniciOzetResource($this->whenLoaded('gonderen')),
            'mesaj_tipi' => $this->mesaj_tipi,
            'mesaj_metni' => $text,
            'dil_kodu' => $this->dil_kodu,
            'dil_adi' => $this->dil_adi ?: Language::name($this->dil_kodu),
            'ceviri' => null,
            'dosya_yolu' => MediaUrl::resolve($this->dosya_yolu),
            'dosya_suresi' => $this->dosya_suresi,
            'okundu_mu' => $this->okundu_mu,
            'ai_tarafindan_uretildi_mi' => $this->ai_tarafindan_uretildi_mi,
            'cevaplanan_mesaj_id' => $this->cevaplanan_mesaj_id,
            'created_at' => $this->created_at,
        ];
    }
}
