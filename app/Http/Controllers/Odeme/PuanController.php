<?php

namespace App\Http\Controllers\Odeme;

use App\Http\Controllers\Controller;
use App\Http\Resources\PuanHareketResource;
use App\Models\PuanHareketi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PuanController extends Controller
{
    public function bakiye(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'mevcut_puan' => $user->mevcut_puan,
            'toplam_harcanan_puan' => abs((int) $user->puanHareketleri()->where('puan_miktari', '<', 0)->sum('puan_miktari')),
        ]);
    }

    public function hareketler(Request $request)
    {
        $hareketler = PuanHareketi::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return PuanHareketResource::collection($hareketler);
    }
}
