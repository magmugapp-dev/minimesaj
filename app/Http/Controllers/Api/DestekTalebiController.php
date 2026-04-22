<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DestekTalebiGonderRequest;
use App\Models\DestekTalebi;
use App\Notifications\DestekTalebiOlustu;
use App\Services\AyarServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class DestekTalebiController extends Controller
{
    public function __construct(private AyarServisi $ayarServisi) {}

    public function gonder(DestekTalebiGonderRequest $request): JsonResponse
    {
        $talep = DestekTalebi::create([
            'user_id' => $request->user()->id,
            'mesaj' => trim((string) $request->validated('mesaj')),
            'durum' => 'yeni',
        ]);

        $talep->load('user');

        $destekEposta = trim((string) ($this->ayarServisi->al('destek_eposta') ?? ''));
        if ($destekEposta !== '') {
            Notification::route('mail', $destekEposta)
                ->notify(new DestekTalebiOlustu(
                    talep: $talep,
                    uygulamaAdi: (string) (
                        $this->ayarServisi->al('site_adi')
                        ?? $this->ayarServisi->al('uygulama_adi', 'MiniMesaj')
                    ),
                ));
        }

        return response()->json([
            'durum' => true,
            'mesaj' => 'Destek talebiniz alindi.',
            'veri' => [
                'id' => $talep->id,
            ],
        ], 201);
    }
}
