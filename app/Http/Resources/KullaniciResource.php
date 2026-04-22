<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KullaniciResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ad' => $this->ad,
            'soyad' => $this->soyad,
            'kullanici_adi' => $this->kullanici_adi,
            'email' => $this->when($this->id === $request->user()?->id, $this->email),
            'hesap_tipi' => $this->hesap_tipi,
            'hesap_durumu' => $this->hesap_durumu,
            'dogum_yili' => $this->dogum_yili,
            'cinsiyet' => $this->cinsiyet,
            'ulke' => $this->ulke,
            'il' => $this->il,
            'ilce' => $this->ilce,
            'biyografi' => $this->biyografi,
            'profil_resmi' => MediaUrl::resolve($this->profil_resmi),
            'cevrim_ici_mi' => $this->cevrim_ici_mi,
            'gorunum_modu' => $this->when($this->id === $request->user()?->id, $this->gorunum_modu),
            'ses_acik_mi' => $this->when($this->id === $request->user()?->id, $this->ses_acik_mi),
            'bildirimler_acik_mi' => $this->when($this->id === $request->user()?->id, $this->bildirimler_acik_mi),
            'titresim_acik_mi' => $this->when($this->id === $request->user()?->id, $this->titresim_acik_mi),
            'mevcut_puan' => $this->when($this->id === $request->user()?->id, $this->mevcut_puan),
            'gunluk_ucretsiz_hak' => $this->when($this->id === $request->user()?->id, $this->gunluk_ucretsiz_hak),
            'eslesme_cinsiyet_filtresi' => $this->when($this->id === $request->user()?->id, $this->eslesme_cinsiyet_filtresi),
            'eslesme_yas_filtresi' => $this->when($this->id === $request->user()?->id, $this->eslesme_yas_filtresi),
            'super_eslesme_aktif_mi' => $this->when($this->id === $request->user()?->id, $this->super_eslesme_aktif_mi),
            'premium_aktif_mi' => $this->premium_aktif_mi,
            'premium_bitis_tarihi' => $this->when($this->id === $request->user()?->id, $this->premium_bitis_tarihi),
            'son_gorulme_tarihi' => $this->son_gorulme_tarihi,
            'fotograflar' => FotografResource::collection($this->whenLoaded('fotograflar')),
            'ai_ayar' => new AiAyarResource($this->whenLoaded('aiAyar')),
            'created_at' => $this->created_at,
        ];
    }
}
