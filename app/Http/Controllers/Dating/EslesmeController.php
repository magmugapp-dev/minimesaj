<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Resources\EslesmeResource;
use App\Models\Eslesme;
use App\Services\EslesmeServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EslesmeController extends Controller
{
    public function __construct(private EslesmeServisi $eslesmeServisi) {}

    public function listele(Request $request)
    {
        $eslesmeler = Eslesme::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
                ->orWhere('eslesen_user_id', $request->user()->id);
        })
            ->where('durum', 'aktif')
            ->with(['user:id,ad,kullanici_adi,profil_resmi', 'eslesenUser:id,ad,kullanici_adi,profil_resmi', 'sohbet'])
            ->latest()
            ->paginate(20);

        return EslesmeResource::collection($eslesmeler);
    }

    public function bitir(Request $request, Eslesme $eslesme): JsonResponse
    {
        Gate::authorize('bitir', $eslesme);

        $this->eslesmeServisi->bitir($eslesme, $request->user());

        return response()->json(['mesaj' => 'Eşleşme sonlandırıldı.']);
    }
}
