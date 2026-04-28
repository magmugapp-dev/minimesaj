<?php

namespace App\Http\Resources;

use App\Models\Engelleme;
use App\Models\SessizeAlinanKullanici;
use App\Services\Users\UserOnlineStatusService;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KullaniciResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $kendiVerisiGorulebilir = $this->kendiVerisiGorulebilir($request);
        $istekYapanId = $request->user()?->id;
        $profilDetayiGoruntuleniyor = $request->is('api/dating/profil/*');
        $onlineStatus = app(UserOnlineStatusService::class)->resolve($this->resource, withNextActiveAt: false);

        return [
            'id' => $this->id,
            'ad' => $this->ad,
            'soyad' => $this->soyad,
            'kullanici_adi' => $this->kullanici_adi,
            'email' => $this->when($kendiVerisiGorulebilir, $this->email),
            'hesap_tipi' => $this->hesap_tipi,
            'hesap_durumu' => $this->hesap_durumu,
            'dogum_yili' => $this->dogum_yili,
            'cinsiyet' => $this->cinsiyet,
            'ulke' => $this->ulke,
            'il' => $this->il,
            'ilce' => $this->ilce,
            'biyografi' => $this->biyografi,
            'profil_resmi' => MediaUrl::resolve($this->profil_resmi),
            'cevrim_ici_mi' => $onlineStatus['is_online'],
            'isOnline' => $onlineStatus['is_online'],
            'onlineStatusReason' => $onlineStatus['reason'],
            'gorunum_modu' => $this->when($kendiVerisiGorulebilir, $this->gorunum_modu),
            'ses_acik_mi' => $this->when($kendiVerisiGorulebilir, $this->ses_acik_mi),
            'bildirimler_acik_mi' => $this->when($kendiVerisiGorulebilir, $this->bildirimler_acik_mi),
            'titresim_acik_mi' => $this->when($kendiVerisiGorulebilir, $this->titresim_acik_mi),
            'dil' => $this->when($kendiVerisiGorulebilir, $this->dil),
            'mevcut_puan' => $this->when($kendiVerisiGorulebilir, $this->mevcut_puan),
            'gunluk_ucretsiz_hak' => $this->when($kendiVerisiGorulebilir, $this->gunluk_ucretsiz_hak),
            'eslesme_cinsiyet_filtresi' => $this->when($kendiVerisiGorulebilir, $this->eslesme_cinsiyet_filtresi),
            'eslesme_yas_filtresi' => $this->when($kendiVerisiGorulebilir, $this->eslesme_yas_filtresi),
            'super_eslesme_aktif_mi' => $this->when($kendiVerisiGorulebilir, $this->super_eslesme_aktif_mi),
            'premium_aktif_mi' => $this->premium_aktif_mi,
            'premium_bitis_tarihi' => $this->when($kendiVerisiGorulebilir, $this->premium_bitis_tarihi),
            'son_gorulme_tarihi' => $this->son_gorulme_tarihi,
            'engellendi_mi' => $this->when(
                $profilDetayiGoruntuleniyor && $istekYapanId !== null && (int) $istekYapanId !== (int) $this->id,
                fn () => Engelleme::query()
                    ->where('engelleyen_user_id', $istekYapanId)
                    ->where('engellenen_user_id', $this->id)
                    ->exists()
            ),
            'sessize_alindi_mi' => $this->when(
                $profilDetayiGoruntuleniyor && $istekYapanId !== null && (int) $istekYapanId !== (int) $this->id,
                fn () => SessizeAlinanKullanici::aktifKayitVarMi((int) $istekYapanId, (int) $this->id)
            ),
            'sessiz_bitis_tarihi' => $this->when(
                $profilDetayiGoruntuleniyor && $istekYapanId !== null && (int) $istekYapanId !== (int) $this->id,
                fn () => SessizeAlinanKullanici::query()
                    ->where('user_id', $istekYapanId)
                    ->where('sessize_alinan_user_id', $this->id)
                    ->where(function ($query) {
                        $query->whereNull('sessiz_bitis_tarihi')
                            ->orWhere('sessiz_bitis_tarihi', '>', now());
                    })
                    ->value('sessiz_bitis_tarihi')
            ),
            'alinan_hediyeler' => $this->when(
                $profilDetayiGoruntuleniyor,
                fn () => HediyeGonderimiResource::collection($this->whenLoaded('aldigiHediyeler'))
            ),
            'fotograflar' => FotografResource::collection($this->whenLoaded('fotograflar')),
            'ai_character' => $this->whenLoaded('aiCharacter', fn () => [
                'character_id' => $this->aiCharacter?->character_id,
                'character_version' => $this->aiCharacter?->character_version,
                'schema_version' => $this->aiCharacter?->schema_version,
                'active' => (bool) $this->aiCharacter?->active,
                'display_name' => $this->aiCharacter?->display_name,
                'primary_language_code' => $this->aiCharacter?->primary_language_code,
                'primary_language_name' => $this->aiCharacter?->primary_language_name,
                'model_name' => $this->aiCharacter?->model_name,
                'character_json' => $this->aiCharacter?->character_json,
            ]),
            'created_at' => $this->created_at,
        ];
    }

    private function kendiVerisiGorulebilir(Request $request): bool
    {
        if ($this->id === $request->user()?->id) {
            return true;
        }

        return $request->is(
            'api/auth/kayit',
            'api/auth/giris',
            'api/auth/sosyal/giris',
            'api/auth/sosyal/kayit',
        );
    }
}
