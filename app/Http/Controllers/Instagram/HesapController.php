<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instagram\HesapBaglaRequest;
use App\Http\Resources\InstagramHesapResource;
use App\Models\InstagramHesap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HesapController extends Controller
{
    public function listele(Request $request): JsonResponse
    {
        return InstagramHesapResource::collection(
            $request->user()->instagramHesaplari()->get()
        )->response();
    }

    public function bagla(HesapBaglaRequest $request): JsonResponse
    {
        $veri = $request->validated();

        $hesap = $request->user()->instagramHesaplari()->updateOrCreate(
            ['instagram_kullanici_adi' => $veri['instagram_kullanici_adi']],
            [
                ...collect($veri)->except('instagram_kullanici_adi')->toArray(),
                'son_baglanti_tarihi' => now(),
            ]
        );

        return (new InstagramHesapResource($hesap))
            ->response()
            ->setStatusCode($hesap->wasRecentlyCreated ? 201 : 200);
    }

    public function kaldir(Request $request, InstagramHesap $hesap): JsonResponse
    {
        Gate::authorize('yonet', $hesap);

        $hesap->delete();

        return response()->json(['mesaj' => 'Hesap bağlantısı kaldırıldı.']);
    }
}
