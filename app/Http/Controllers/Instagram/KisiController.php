<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instagram\KisiSenkronizeRequest;
use App\Http\Resources\InstagramKisiResource;
use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KisiController extends Controller
{
    public function listele(Request $request, InstagramHesap $hesap)
    {
        $this->yetkilendir($request, $hesap);

        return InstagramKisiResource::collection(
            $hesap->kisiler()->orderByDesc('son_mesaj_tarihi')->paginate(50)
        );
    }

    public function senkronize(KisiSenkronizeRequest $request, InstagramHesap $hesap): JsonResponse
    {
        $this->yetkilendir($request, $hesap);

        $veri = $request->validated();

        $sonuclar = [];
        foreach ($veri['kisiler'] as $kisiVeri) {
            $sonuclar[] = InstagramKisi::updateOrCreate(
                [
                    'instagram_hesap_id' => $hesap->id,
                    'instagram_kisi_id' => $kisiVeri['instagram_kisi_id'],
                ],
                [
                    'kullanici_adi' => $kisiVeri['kullanici_adi'] ?? null,
                    'gorunen_ad' => $kisiVeri['gorunen_ad'] ?? null,
                    'profil_resmi' => $kisiVeri['profil_resmi'] ?? null,
                ]
            );
        }

        return response()->json(['senkronize_edilen' => count($sonuclar)]);
    }

    private function yetkilendir(Request $request, InstagramHesap $hesap): void
    {
        Gate::authorize('yonet', $hesap);
    }
}
