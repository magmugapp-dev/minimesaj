<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dating\ProfilGuncelleRequest;
use App\Http\Resources\KullaniciResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfilController extends Controller
{
    public function goster(Request $request): JsonResponse|KullaniciResource
    {
        return new KullaniciResource(
            $request->user()->load('fotograflar', 'aiAyar', 'availabilitySchedules')
        );
    }

    public function kullanici(Request $request, User $kullanici): KullaniciResource
    {
        if ($kullanici->is_admin) {
            throw new NotFoundHttpException();
        }

        return new KullaniciResource(
            $kullanici->load([
                'fotograflar' => fn ($query) => $query
                    ->where('aktif_mi', true)
                    ->orderBy('sira_no')
                    ->orderByDesc('ana_fotograf_mi'),
                'aldigiHediyeler' => fn ($query) => $query
                    ->with(['hediye', 'gonderen:id,ad,soyad,kullanici_adi,profil_resmi'])
                    ->latest(),
                'aiAyar',
                'availabilitySchedules',
            ])
        );
    }

    public function influencer(Request $request, User $kullanici): KullaniciResource
    {
        if ($kullanici->is_admin || $kullanici->hesap_tipi !== 'ai' || $kullanici->hesap_durumu !== 'aktif') {
            throw new NotFoundHttpException();
        }

        return $this->kullanici($request, $kullanici);
    }

    public function guncelle(ProfilGuncelleRequest $request)
    {
        $veri = $request->validated();

        $request->user()->update($veri);

        return new KullaniciResource($request->user()->fresh());
    }
}
