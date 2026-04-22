<?php

namespace App\Http\Controllers\Yonetim;

use App\Http\Controllers\Controller;
use App\Http\Requests\Yonetim\SikayetYonetimGuncelleRequest;
use App\Http\Resources\SikayetResource;
use App\Models\Sikayet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SikayetYonetimController extends Controller
{
    public function listele(Request $request): JsonResponse
    {
        $sorgu = Sikayet::with(['sikayetEden:id,ad,soyad,kullanici_adi'])
            ->orderByDesc('id');

        if ($request->has('durum')) {
            $sorgu->where('durum', $request->input('durum'));
        }

        return response()->json($sorgu->paginate(20));
    }

    public function guncelle(SikayetYonetimGuncelleRequest $request, Sikayet $sikayet): JsonResponse|SikayetResource
    {
        $veri = $request->validated();

        $sikayet->update($veri);

        return new SikayetResource($sikayet);
    }
}
