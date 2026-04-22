<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AyarServisi;
use App\Services\Odeme\MobilOdemeAyarServisi;
use Illuminate\Support\Facades\Storage;

class UygulamaAyarController extends Controller
{
    public function __construct(
        private AyarServisi $ayarServisi,
        private MobilOdemeAyarServisi $mobilOdemeAyarServisi,
    ) {}

    /**
     * Flutter uygulaması için genel ayarları döndürür (logo, uygulama adı vb.)
     */
    public function index()
    {
        $logoYolu = $this->ayarServisi->al('flutter_logosu');
        $logoVarMi = $logoYolu && Storage::disk('public')->exists($logoYolu);
        $logoUrl = null;
        $googlePlayDurumu = $this->mobilOdemeAyarServisi->platformDurumu('android');
        $appStoreDurumu = $this->mobilOdemeAyarServisi->platformDurumu('ios');

        if ($logoVarMi) {
            $logoUrl = asset('storage/' . $logoYolu);
        }

        $uygulamaAdi = $this->nullableString('site_adi')
            ?? $this->nullableString('uygulama_adi')
            ?? 'MiniMesaj';

        return response()->json([
            'durum' => true,
            'veri' => [
                'uygulama_adi' => $uygulamaAdi,
                'uygulama_logosu' => $logoUrl,
                'uygulama_versiyonu' => $this->nullableString('uygulama_versiyonu'),
                'mobil_minimum_versiyon' => $this->nullableString('mobil_minimum_versiyon'),
                'varsayilan_dil' => $this->nullableString('varsayilan_dil') ?? 'tr',
                'kayit_aktif_mi' => (bool) $this->ayarServisi->al('kayit_aktif_mi', true),
                'destek_eposta' => $this->nullableString('destek_eposta'),
                'destek_whatsapp' => $this->nullableString('destek_whatsapp'),
                'android_play_store_url' => $this->nullableString('android_play_store_url'),
                'ios_app_store_url' => $this->nullableString('ios_app_store_url'),
                'odeme_kanallari' => [
                    'google_play' => $googlePlayDurumu + [
                        'kullanilabilir' => $googlePlayDurumu['aktif'] && $googlePlayDurumu['hazir'],
                    ],
                    'app_store' => $appStoreDurumu + [
                        'kullanilabilir' => $appStoreDurumu['aktif'] && $appStoreDurumu['hazir'],
                    ],
                ],
                'logo_guncelleme_zamani' => $logoVarMi
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

    private function nullableString(string $anahtar): ?string
    {
        $deger = $this->ayarServisi->al($anahtar);

        if ($deger === null) {
            return null;
        }

        $metin = trim((string) $deger);

        return $metin === '' ? null : $metin;
    }
}
