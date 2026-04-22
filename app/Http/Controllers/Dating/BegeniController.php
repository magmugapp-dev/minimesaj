<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Resources\BegeniResource;
use App\Models\User;
use App\Services\EslesmeServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BegeniController extends Controller
{
    public function __construct(private EslesmeServisi $eslesmeServisi) {}

    public function begen(Request $request, User $kullanici): JsonResponse
    {
        if ($kullanici->id === $request->user()->id) {
            abort(422, 'Kendinizi beğenemezsiniz.');
        }

        $sonuc = $this->eslesmeServisi->begen($request->user(), $kullanici);

        return response()->json($sonuc);
    }

    public function gelenler(Request $request)
    {
        $begeniler = $request->user()
            ->gelenBegeniler()
            ->with('begenen:id,ad,kullanici_adi,profil_resmi')
            ->where('eslesmeye_donustu_mu', false)
            ->latest()
            ->paginate(20);

        return BegeniResource::collection($begeniler);
    }
}
