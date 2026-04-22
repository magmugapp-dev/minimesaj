<?php

namespace App\Http\Controllers\Odeme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Odeme\ReklamOdulKaydetRequest;
use App\Models\ReklamOdulu;
use App\Services\PuanServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReklamOdulController extends Controller
{
    public function __construct(private PuanServisi $puanServisi) {}

    public function kaydet(ReklamOdulKaydetRequest $request): JsonResponse
    {
        $veri = $request->validated();

        // Günlük limit kontrolü
        $bugunSayisi = ReklamOdulu::where('user_id', $request->user()->id)
            ->whereDate('created_at', today())
            ->count();

        if ($bugunSayisi >= 10) {
            return response()->json(['mesaj' => 'Günlük reklam ödül limitine ulaştınız.'], 429);
        }

        $puanMiktari = 5; // Reklam başına sabit puan

        $odul = ReklamOdulu::create([
            'user_id' => $request->user()->id,
            'reklam_platformu' => $veri['reklam_platformu'],
            'reklam_birim_kodu' => $veri['reklam_birim_kodu'],
            'puan_miktari' => $puanMiktari,
        ]);

        $this->puanServisi->ekle(
            $request->user(),
            $puanMiktari,
            'reklam_odulu',
            'Reklam izleme ödülü',
            'reklam_odulu',
            $odul->id,
        );

        return response()->json([
            'mesaj' => "Ödül kazandınız: +{$puanMiktari} puan",
            'kalan_hak' => 10 - $bugunSayisi - 1,
        ], 201);
    }
}
