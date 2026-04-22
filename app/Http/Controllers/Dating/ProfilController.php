<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dating\ProfilGuncelleRequest;
use App\Http\Resources\KullaniciResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilController extends Controller
{
    public function goster(Request $request): JsonResponse|KullaniciResource
    {
        return new KullaniciResource(
            $request->user()->load('fotograflar', 'aiAyar')
        );
    }

    public function guncelle(ProfilGuncelleRequest $request)
    {
        $veri = $request->validated();

        $request->user()->update($veri);

        return new KullaniciResource($request->user()->fresh());
    }
}
