<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dating\MesajGonderRequest;
use App\Http\Resources\MesajResource;
use App\Models\Sohbet;
use App\Services\MesajServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MesajController extends Controller
{
    public function __construct(private MesajServisi $mesajServisi) {}

    public function listele(Request $request, Sohbet $sohbet)
    {
        $this->yetkilendir($request, $sohbet);

        $mesajlar = $sohbet->mesajlar()
            ->with('gonderen:id,ad,kullanici_adi,profil_resmi')
            ->latest()
            ->paginate(50);

        return MesajResource::collection($mesajlar);
    }

    public function gonder(MesajGonderRequest $request, Sohbet $sohbet): JsonResponse
    {
        $this->yetkilendir($request, $sohbet);

        $veri = $request->validated();

        $mesaj = $this->mesajServisi->gonder($sohbet, $request->user(), $veri);

        return (new MesajResource($mesaj->load('gonderen:id,ad,kullanici_adi,profil_resmi')))
            ->response()
            ->setStatusCode(201);
    }

    public function okuduIsaretle(Request $request, Sohbet $sohbet): JsonResponse
    {
        $this->yetkilendir($request, $sohbet);

        $okunan = $this->mesajServisi->okuduIsaretle($sohbet, $request->user());

        return response()->json(['okunan_mesaj_sayisi' => $okunan]);
    }

    private function yetkilendir(Request $request, Sohbet $sohbet): void
    {
        Gate::authorize('erisebilir', $sohbet);
    }
}
