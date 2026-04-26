<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Resources\BildirimResource;
use App\Http\Resources\KullaniciResource;
use App\Models\PushDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BildirimController extends Controller
{
    public function listele(Request $request)
    {
        $bugun = now();
        $bildirimler = $request->user()
            ->notifications()
            ->whereDate('created_at', $bugun->toDateString())
            ->latest()
            ->paginate(20);

        return BildirimResource::collection($bildirimler);
    }

    public function okunmamisSayisi(Request $request): JsonResponse
    {
        return response()->json([
            'okunmamis_sayisi' => $request->user()
                ->unreadNotifications()
                ->whereDate('created_at', now()->toDateString())
                ->count(),
        ]);
    }

    public function okuduIsaretle(Request $request): JsonResponse
    {
        $request->user()
            ->unreadNotifications()
            ->whereDate('created_at', now()->toDateString())
            ->get()
            ->markAsRead();

        return response()->json(['mesaj' => 'Tum bildirimler okundu olarak isaretlendi.']);
    }

    public function tekOku(Request $request, string $bildirimId): JsonResponse
    {
        $bildirim = $request->user()
            ->notifications()
            ->where('id', $bildirimId)
            ->firstOrFail();

        $bildirim->markAsRead();

        return response()->json(['mesaj' => 'Bildirim okundu.']);
    }

    public function cihazKaydet(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'token' => 'required|string',
            'platform' => 'required|in:android,ios,web',
            'cihaz_adi' => 'nullable|string|max:255',
            'uygulama_versiyonu' => 'nullable|string|max:100',
            'dil' => 'nullable|string|max:12',
            'bildirim_izni' => 'required|boolean',
        ]);

        $cihaz = PushDeviceToken::query()->firstOrNew([
            'token' => $veri['token'],
        ]);

        $olusturuldu = !$cihaz->exists;

        $cihaz->fill($veri);
        $cihaz->user()->associate($request->user());
        $cihaz->son_gorulme_at = now();
        $cihaz->save();

        return response()->json([
            'mesaj' => 'Bildirim cihazi kaydedildi.',
            'cihaz' => [
                'id' => $cihaz->id,
                'platform' => $cihaz->platform,
                'bildirim_izni' => $cihaz->bildirim_izni,
                'son_gorulme_at' => $cihaz->son_gorulme_at,
            ],
        ], $olusturuldu ? 201 : 200);
    }

    public function cihazSil(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'token' => 'required|string',
        ]);

        PushDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $veri['token'])
            ->delete();

        return response()->json([
            'mesaj' => 'Bildirim cihazi silindi.',
        ]);
    }

    public function ayarGuncelle(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'bildirimler_acik_mi' => 'sometimes|boolean',
            'titresim_acik_mi' => 'sometimes|boolean',
            'ses_acik_mi' => 'sometimes|boolean',
        ]);

        $request->user()->update($veri);

        return response()->json([
            'mesaj' => 'Bildirim ayarlari guncellendi.',
            'kullanici' => new KullaniciResource($request->user()->fresh()),
        ]);
    }
}
