<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Resources\KullaniciResource;
use App\Models\Begeni;
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

        $begenilen = Begeni::query()
            ->where('begenen_user_id', $user->id)
            ->pluck('begenilen_user_id');

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
            ->merge($begenilen)
            ->merge($eslesilen)
            ->push($user->id)
            ->unique()
            ->values();

        $adaylar = User::query()
            ->whereIn('hesap_tipi', ['user', 'ai'])
            ->where('hesap_durumu', 'aktif')
            ->where('cevrim_ici_mi', true)
            ->whereNotIn('id', $haricTutulanlar)
            ->with('fotograflar')
            ->orderByRaw("CASE WHEN hesap_tipi = 'user' THEN 0 ELSE 1 END")
            ->orderByDesc('cevrim_ici_mi')
            ->inRandomOrder()
            ->paginate(20);

        return KullaniciResource::collection($adaylar);
    }
}
