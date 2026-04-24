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
        $peerLanguage = $this->peerLanguage($peer);

        return [
            'id' => $this->id,
            'eslesme_id' => $this->eslesme_id,
            'eslesme' => new EslesmeResource($this->whenLoaded('eslesme')),
            'son_mesaj' => new MesajResource($this->whenLoaded('sonMesaj')),
            'son_mesaj_tarihi' => $this->son_mesaj_tarihi,
            'ai_durumu' => $this->ai_durumu,
            'ai_durum_metni' => $this->normalizeAiStatusText(),
            'ai_planlanan_cevap_at' => $this->ai_planlanan_cevap_at,
            'toplam_mesaj_sayisi' => $this->toplam_mesaj_sayisi,
            'okunmamis_sayisi' => $this->okunmamis_sayisi,
            'peer_language_code' => $peerLanguage['code'],
            'peer_language_name' => $peerLanguage['name'],
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

    private function peerLanguage(mixed $peer): array
    {
        if (!$peer) {
            return ['code' => null, 'name' => null];
        }

        $persona = $peer->relationLoaded('aiPersonaProfile') ? $peer->aiPersonaProfile : null;
        $code = $peer->hesap_tipi === 'ai'
            ? Language::normalizeCode($persona?->ana_dil_kodu) ?: Language::normalizeCode($peer->dil)
            : Language::normalizeCode($peer->dil);
        $name = $peer->hesap_tipi === 'ai'
            ? ($persona?->ana_dil_adi ?: Language::name($code))
            : Language::name($code);

        return [
            'code' => $code,
            'name' => $name,
        ];
    }

    private function normalizeAiStatusText(): ?string
    {
        $normalized = trim((string) $this->ai_durum_metni);

        if ($normalized === 'Dusunuyor...') {
            return 'Yaziyor...';
        }

        if ($normalized !== '') {
            return $normalized;
        }

        return in_array($this->ai_durumu, ['queued', 'typing'], true) ? 'Yaziyor...' : null;
    }
}
