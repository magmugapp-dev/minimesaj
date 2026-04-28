<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Resources\KullaniciResource;
use App\Http\Resources\SohbetResource;
use App\Models\User;
use App\Services\EslesmeServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EslesmeMerkeziController extends Controller
{
    public function __construct(private EslesmeServisi $eslesmeServisi) {}

    public function merkez(Request $request): JsonResponse
    {
        return response()->json(
            $this->eslesmeServisi->merkez($request->user()),
        );
    }

    public function tercihleriGuncelle(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'cinsiyet' => ['required', 'in:tum,kadin,erkek'],
            'yas' => ['required', 'in:tum,18_25,26_35,36_ustu'],
            'super_eslesme_aktif_mi' => ['required', 'boolean'],
        ]);

        $kullanici = $this->eslesmeServisi->tercihleriGuncelle($request->user(), $veri);

        return response()->json([
            'mesaj' => 'Eşleşme tercihleri güncellendi.',
            'filtreler' => [
                'cinsiyet' => $kullanici->eslesme_cinsiyet_filtresi,
                'yas' => $kullanici->eslesme_yas_filtresi,
                'super_eslesme_aktif_mi' => (bool) $kullanici->super_eslesme_aktif_mi,
            ],
        ]);
    }

    public function baslat(Request $request): JsonResponse
    {
        $sonuc = $this->eslesmeServisi->eslesmeBaslat($request->user());

        if (($sonuc['durum'] ?? null) === 'yetersiz_puan') {
            return response()->json([
                'mesaj' => 'Eşleşmeyi başlatmak için daha fazla puan gerekli.',
                ...$sonuc,
            ], 402);
        }

        if (($sonuc['durum'] ?? null) === 'aday_bulundu') {
            return response()->json([
                'durum' => 'aday_bulundu',
                'aday' => KullaniciResource::make($sonuc['aday'])->resolve($request),
                'mevcut_puan' => $sonuc['mevcut_puan'],
                'gunluk_ucretsiz_hak' => $sonuc['gunluk_ucretsiz_hak'] ?? null,
                'eslesme_baslatma_maliyeti' => $sonuc['eslesme_baslatma_maliyeti'],
                'ucretsiz_hak_kullanildi' => $sonuc['ucretsiz_hak_kullanildi'] ?? false,
            ]);
        }

        return response()->json([
            'mesaj' => 'Şu anda uygun eşleşme bulunamadı.',
            ...$sonuc,
        ]);
    }

    public function gec(Request $request, User $kullanici): JsonResponse
    {
        $this->eslesmeServisi->adayGec($request->user(), $kullanici);

        return response()->json([
            'mesaj' => 'Aday gecildi.',
        ]);
    }

    public function sohbetBaslat(Request $request, User $kullanici): JsonResponse
    {
        $sonuc = $this->eslesmeServisi->sohbetBaslat($request->user(), $kullanici);

        if (($sonuc['durum'] ?? null) === 'engellendi') {
            return response()->json([
                'durum' => 'engellendi',
                'mesaj' => 'Bu kullanici ile sohbet baslatilamaz.',
            ], 422);
        }

        if (($sonuc['durum'] ?? null) !== 'eslesme') {
            return response()->json([
                'durum' => $sonuc['durum'] ?? 'aday_gecersiz',
                'mesaj' => 'Bu aday ile sohbet baslatilamaz.',
            ], 422);
        }

        return response()->json([
            'durum' => 'eslesme',
            'mesaj' => 'Sohbet hazir.',
            'eslesme_id' => $sonuc['eslesme_id'],
            'sohbet_id' => $sonuc['sohbet_id'],
            'sohbet' => SohbetResource::make(
                $sonuc['sohbet']->loadMissing([
                    'eslesme.user.aiCharacter:id,user_id,character_id,character_version,schema_version,active,display_name,primary_language_code,primary_language_name,model_name,character_json',
                    'eslesme.eslesenUser.aiCharacter:id,user_id,character_id,character_version,schema_version,active,display_name,primary_language_code,primary_language_name,model_name,character_json',
                ])
            )->resolve($request),
        ]);
    }
}
