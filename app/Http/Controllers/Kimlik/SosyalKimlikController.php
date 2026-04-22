<?php

namespace App\Http\Controllers\Kimlik;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kimlik\KullaniciAdiMusaitRequest;
use App\Http\Requests\Kimlik\SosyalGirisRequest;
use App\Http\Requests\Kimlik\SosyalKayitRequest;
use App\Http\Resources\KullaniciResource;
use App\Services\Kimlik\Sosyal\SosyalAuthServisi;
use Illuminate\Http\JsonResponse;

class SosyalKimlikController extends Controller
{
    public function __construct(private SosyalAuthServisi $sosyalAuthServisi) {}

    public function giris(SosyalGirisRequest $request): JsonResponse
    {
        $sonuc = $this->sosyalAuthServisi->giris($request->validated());

        if ($sonuc['durum'] === 'authenticated') {
            return response()->json([
                'durum' => 'authenticated',
                'kullanici' => new KullaniciResource($sonuc['user']),
                'token' => $sonuc['token'],
            ]);
        }

        return response()->json([
            'durum' => 'onboarding_required',
            'social_session' => $sonuc['social_session'],
            'prefill' => $sonuc['prefill'],
        ]);
    }

    public function kayit(SosyalKayitRequest $request): JsonResponse
    {
        $sonuc = $this->sosyalAuthServisi->kayit(
            $request->validated(),
            $request->file('dosya'),
        );

        return response()->json([
            'durum' => 'authenticated',
            'kullanici' => new KullaniciResource($sonuc['user']),
            'token' => $sonuc['token'],
        ], $sonuc['status']);
    }

    public function kullaniciAdiMusait(KullaniciAdiMusaitRequest $request): JsonResponse
    {
        return response()->json([
            'musait' => $this->sosyalAuthServisi->kullaniciAdiMusait(
                $request->validated('kullanici_adi'),
            ),
        ]);
    }
}
