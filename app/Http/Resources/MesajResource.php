<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use App\Support\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MesajResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sohbet_id' => $this->sohbet_id,
            'gonderen' => new KullaniciOzetResource($this->whenLoaded('gonderen')),
            'mesaj_tipi' => $this->mesaj_tipi,
            'mesaj_metni' => $this->mesaj_metni,
            'dil_kodu' => $this->dil_kodu,
            'dil_adi' => $this->dil_adi ?: Language::name($this->dil_kodu),
            'ceviri' => $this->cachedTranslationFor($request),
            'dosya_yolu' => MediaUrl::resolve($this->dosya_yolu),
            'dosya_suresi' => $this->dosya_suresi,
            'okundu_mu' => $this->okundu_mu,
            'ai_tarafindan_uretildi_mi' => $this->ai_tarafindan_uretildi_mi,
            'cevaplanan_mesaj_id' => $this->cevaplanan_mesaj_id,
            'created_at' => $this->created_at,
        ];
    }

    private function cachedTranslationFor(Request $request): ?array
    {
        $targetCode = Language::normalizeCode($request->user()?->dil) ?: 'tr';
        $translations = $this->ceviriler;

        if (!is_array($translations) || !isset($translations[$targetCode])) {
            return null;
        }

        return $translations[$targetCode];
    }
}
