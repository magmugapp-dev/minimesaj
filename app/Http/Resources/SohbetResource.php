<?php

namespace App\Http\Resources;

use App\Support\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SohbetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $peer = $this->peerFor($request);

        return [
            'id' => $this->id,
            'eslesme_id' => $this->eslesme_id,
            'eslesme' => new EslesmeResource($this->whenLoaded('eslesme')),
            'son_mesaj' => new MesajResource($this->whenLoaded('sonMesaj')),
            'son_mesaj_tarihi' => $this->son_mesaj_tarihi,
            'ai_durumu' => $this->ai_durumu,
            'ai_durum_metni' => $this->ai_durum_metni,
            'ai_planlanan_cevap_at' => $this->ai_planlanan_cevap_at,
            'toplam_mesaj_sayisi' => $this->toplam_mesaj_sayisi,
            'okunmamis_sayisi' => $this->okunmamis_sayisi,
            'peer_language_code' => $peer?->dil,
            'peer_language_name' => Language::name($peer?->dil),
            'durum' => $this->durum,
            'created_at' => $this->created_at,
        ];
    }

    private function peerFor(Request $request): mixed
    {
        if (!$this->resource->relationLoaded('eslesme') || !$request->user()) {
            return null;
        }

        $match = $this->resource->eslesme;

        return (int) $match->user_id === (int) $request->user()->id
            ? $match->eslesenUser
            : $match->user;
    }
}
