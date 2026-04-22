<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AyarServisi;
use Illuminate\Support\Facades\Storage;

class UygulamaAyarController extends Controller
{
    public function __construct(private AyarServisi $ayarServisi) {}

    /**
     * Flutter uygulaması için genel ayarları döndürür (logo, uygulama adı vb.)
     */
    public function index()
    {
        $logoYolu = $this->ayarServisi->al('flutter_logosu');
        $logoUrl = null;

        if ($logoYolu && Storage::disk('public')->exists($logoYolu)) {
            $logoUrl = asset('storage/' . $logoYolu);
        }

        return response()->json([
            'durum' => true,
            'veri' => [
                'uygulama_adi' => $this->ayarServisi->al('uygulama_adi', 'MiniMesaj'),
                'uygulama_logosu' => $logoUrl,
                'logo_guncelleme_zamani' => $logoYolu && Storage::disk('public')->exists($logoYolu)
                    ? Storage::disk('public')->lastModified($logoYolu)
                    : null,
            ],
        ]);
    }

    /**
     * Sadece logo bilgisini döndürür.
     */
    public function logo()
    {
        $logoYolu = $this->ayarServisi->al('flutter_logosu');

        if (!$logoYolu || !Storage::disk('public')->exists($logoYolu)) {
            return response()->json([
                'durum' => false,
                'mesaj' => 'Logo bulunamadı.',
            ], 404);
        }

        $logoUrl = asset('storage/' . $logoYolu);
        $sonDegisiklik = Storage::disk('public')->lastModified($logoYolu);
        $boyut = Storage::disk('public')->size($logoYolu);
        $mime = mime_content_type(Storage::disk('public')->path($logoYolu));

        return response()->json([
            'durum' => true,
            'veri' => [
                'logo_url' => $logoUrl,
                'mime_tipi' => $mime,
                'boyut' => $boyut,
                'guncelleme_zamani' => $sonDegisiklik,
            ],
        ]);
    }
}
