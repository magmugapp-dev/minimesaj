<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Resources\KullaniciResource;
use App\Models\Engelleme;
use App\Models\Eslesme;
use App\Models\User;
use Illuminate\Http\Request;

class KesfetController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $engellenen = Engelleme::query()
            ->where('engelleyen_user_id', $user->id)
            ->pluck('engellenen_user_id')
            ->merge(
                Engelleme::query()
                    ->where('engellenen_user_id', $user->id)
                    ->pluck('engelleyen_user_id')
            );

        $eslesilen = Eslesme::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('eslesen_user_id', $user->id);
            })
            ->where('durum', 'aktif')
            ->get(['user_id', 'eslesen_user_id'])
            ->flatMap(function (Eslesme $eslesme) use ($user) {
                return collect([$eslesme->user_id, $eslesme->eslesen_user_id])
                    ->reject(fn ($id) => (int) $id === (int) $user->id);
            });

        $haricTutulanlar = $engellenen
            ->merge($eslesilen)
            ->push($user->id)
            ->unique()
            ->values();

        $adaySorgusu = User::query()
            ->whereIn('hesap_tipi', ['user', 'ai'])
            ->where('hesap_durumu', 'aktif')
            ->where('cevrim_ici_mi', true)
            ->whereNotIn('id', $haricTutulanlar)
            ->with('fotograflar')
            ->orderByRaw("CASE WHEN hesap_tipi = 'user' THEN 0 ELSE 1 END")
            ->orderByDesc('cevrim_ici_mi')
            ->inRandomOrder();

        if ($request->boolean('profil_resimli')) {
            $adaySorgusu->where(function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNotNull('profil_resmi')
                        ->where('profil_resmi', '<>', '');
                })->orWhereHas('fotograflar', function ($subQuery) {
                    $subQuery->where('aktif_mi', true)
                        ->where('medya_tipi', 'fotograf');
                });
            });
        }

        $sayfaBasina = min(max((int) $request->integer('per_page', 20), 1), 20);

        $adaylar = $adaySorgusu->paginate($sayfaBasina);

        return KullaniciResource::collection($adaylar);
    }
}
