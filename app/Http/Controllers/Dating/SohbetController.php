<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Resources\SohbetResource;
use App\Models\Eslesme;
use App\Models\Sohbet;
use Illuminate\Http\Request;

class SohbetController extends Controller
{
    public function listele(Request $request)
    {
        $userId = $request->user()->id;

        $sohbetler = Sohbet::whereHas('eslesme', function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('eslesen_user_id', $userId);
        })
            ->where('durum', 'aktif')
            ->with([
                'eslesme.user:id,ad,kullanici_adi,profil_resmi,cevrim_ici_mi,dil',
                'eslesme.eslesenUser:id,ad,kullanici_adi,profil_resmi,cevrim_ici_mi,dil',
                'sonMesaj',
            ])
            ->withCount([
                'mesajlar as okunmamis_sayisi' => function ($query) use ($userId) {
                    $query->where('gonderen_user_id', '!=', $userId)
                        ->where('okundu_mu', false);
                },
            ])
            ->orderByDesc('son_mesaj_tarihi')
            ->paginate(20);

        return SohbetResource::collection($sohbetler);
    }
}
