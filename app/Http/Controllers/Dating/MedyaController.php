<?php

namespace App\Http\Controllers\Dating;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dating\MedyaYukleRequest;
use App\Models\MobileUpload;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;

class MedyaController extends Controller
{
    public function yukle(MedyaYukleRequest $request): JsonResponse
    {
        $mesajTipi = $request->validated('mesaj_tipi');
        $kullaniciId = (int) $request->user()->id;
        $clientUploadId = trim((string) ($request->validated('client_upload_id') ?? ''));

        if ($clientUploadId !== '') {
            $mevcut = MobileUpload::query()
                ->where('user_id', $kullaniciId)
                ->where('client_upload_id', $clientUploadId)
                ->first();

            if ($mevcut) {
                return response()->json($this->payload($mevcut), 200);
            }
        }

        $dosya = $request->file('dosya');

        $dosyaYolu = $dosya->store("mesajlar/{$kullaniciId}/{$mesajTipi}", 'public');
        $upload = null;

        if ($clientUploadId !== '') {
            $upload = MobileUpload::create([
                'user_id' => $kullaniciId,
                'client_upload_id' => $clientUploadId,
                'mesaj_tipi' => $mesajTipi,
                'dosya_yolu' => $dosyaYolu,
                'mime_tipi' => $dosya->getMimeType(),
                'boyut' => $dosya->getSize(),
            ]);
        }

        return response()->json($this->payload($upload, [
            'mesaj' => 'Medya yuklendi.',
            'dosya_yolu' => $dosyaYolu,
            'dosya_url' => MediaUrl::resolve($dosyaYolu),
            'mime_tipi' => $dosya->getMimeType(),
            'boyut' => $dosya->getSize(),
        ]), 201);
    }

    private function payload(?MobileUpload $upload, ?array $fallback = null): array
    {
        if ($upload) {
            return [
                'mesaj' => 'Medya yuklendi.',
                'client_upload_id' => $upload->client_upload_id,
                'dosya_yolu' => $upload->dosya_yolu,
                'dosya_url' => MediaUrl::resolve($upload->dosya_yolu),
                'mime_tipi' => $upload->mime_tipi,
                'boyut' => $upload->boyut,
            ];
        }

        return $fallback ?? [];
    }
}
