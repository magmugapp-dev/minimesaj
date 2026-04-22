<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dating\MedyaYukleRequest;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;

class MedyaController extends Controller
{
    public function yukle(MedyaYukleRequest $request): JsonResponse
    {
        $dosya = $request->file('dosya');
        $mesajTipi = $request->validated('mesaj_tipi');
        $kullaniciId = (int) $request->user()->id;

        $dosyaYolu = $dosya->store("mesajlar/{$kullaniciId}/{$mesajTipi}", 'public');

        return response()->json([
            'mesaj' => 'Medya yuklendi.',
            'dosya_yolu' => $dosyaYolu,
            'dosya_url' => MediaUrl::resolve($dosyaYolu),
            'mime_tipi' => $dosya->getMimeType(),
            'boyut' => $dosya->getSize(),
        ], 201);
    }
}

