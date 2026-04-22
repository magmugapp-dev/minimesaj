<?php

namespace App\Http\Controllers\Moderasyon;

use App\Http\Controllers\Controller;
use App\Models\Engelleme;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngellemeController extends Controller
{
    public function engelle(Request $request, User $kullanici): JsonResponse
    {
        if ($kullanici->id === $request->user()->id) {
            abort(422, 'Kendinizi engelleyemezsiniz.');
        }

        Engelleme::firstOrCreate([
            'engelleyen_user_id' => $request->user()->id,
            'engellenen_user_id' => $kullanici->id,
        ], [
            'sebep' => $request->input('sebep'),
        ]);

        return response()->json(['mesaj' => 'Kullanıcı engellendi.']);
    }

    public function kaldir(Request $request, User $kullanici): JsonResponse
    {
        Engelleme::where('engelleyen_user_id', $request->user()->id)
            ->where('engellenen_user_id', $kullanici->id)
            ->delete();

        return response()->json(['mesaj' => 'Engel kaldırıldı.']);
    }
}
