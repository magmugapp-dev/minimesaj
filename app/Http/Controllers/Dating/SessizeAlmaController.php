<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Models\SessizeAlinanKullanici;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessizeAlmaController extends Controller
{
    public function sessizeAl(Request $request, User $kullanici): JsonResponse
    {
        $user = $request->user();

        if ((int) $user->id === (int) $kullanici->id) {
            return response()->json(['mesaj' => 'Kendinizi sessize alamazsiniz.'], 422);
        }

        $veri = $request->validate([
            'sure' => 'nullable|string|in:1_saat,8_saat,1_gun,suresiz',
        ]);

        $bitis = match ($veri['sure'] ?? '8_saat') {
            '1_saat' => now()->addHour(),
            '1_gun' => now()->addDay(),
            'suresiz' => null,
            default => now()->addHours(8),
        };

        $kayit = SessizeAlinanKullanici::updateOrCreate(
            [
                'user_id' => $user->id,
                'sessize_alinan_user_id' => $kullanici->id,
            ],
            ['sessiz_bitis_tarihi' => $bitis]
        );

        return response()->json([
            'durum' => true,
            'sessize_alindi_mi' => true,
            'sessiz_bitis_tarihi' => $kayit->sessiz_bitis_tarihi?->toIso8601String(),
        ]);
    }

    public function kaldir(Request $request, User $kullanici): JsonResponse
    {
        SessizeAlinanKullanici::query()
            ->where('user_id', $request->user()->id)
            ->where('sessize_alinan_user_id', $kullanici->id)
            ->delete();

        return response()->json([
            'durum' => true,
            'sessize_alindi_mi' => false,
            'sessiz_bitis_tarihi' => null,
        ]);
    }
}
