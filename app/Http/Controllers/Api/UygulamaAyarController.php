<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ayar;
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
                'reklamlar' => $this->reklamAyarlari(),
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

    public function yasalMetinler()
    {
        $metinler = [
            'gizlilik_politikasi' => $this->yasalMetinPayload(
                'gizlilik_politikasi',
                'Gizlilik Politikasi'
            ),
            'kvkk_aydinlatma_metni' => $this->yasalMetinPayload(
                'kvkk_aydinlatma_metni',
                'KVKK Aydinlatma Metni'
            ),
            'kullanim_kosullari' => $this->yasalMetinPayload(
                'kullanim_kosullari',
                'Kullanim Kosullari'
            ),
        ];
        $version = sha1(json_encode($metinler, JSON_UNESCAPED_UNICODE));

        return response()
            ->json([
                'durum' => true,
                'veri' => [
                    'version' => $version,
                    'metinler' => $metinler,
                ],
            ])
            ->setEtag($version)
            ->header('Cache-Control', 'public, max-age=300');
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

    private function reklamAyarlari(): array
    {
        return [
            'aktif_mi' => (bool) $this->ayarServisi->al('admob_aktif_mi', false),
            'test_modu' => (bool) $this->ayarServisi->al('admob_test_modu', true),
            'odul_puani' => max(0, (int) $this->ayarServisi->al('reklam_odulu', 15)),
            'gunluk_odul_limiti' => max(0, (int) $this->ayarServisi->al('reklam_gunluk_odul_limiti', 10)),
            'android' => [
                'app_id' => $this->nullableString('admob_android_app_id'),
                'rewarded_unit_id' => $this->nullableString('admob_android_rewarded_unit_id'),
                'match_native_unit_id' => $this->nullableString('admob_android_match_native_unit_id'),
            ],
            'ios' => [
                'app_id' => $this->nullableString('admob_ios_app_id'),
                'rewarded_unit_id' => $this->nullableString('admob_ios_rewarded_unit_id'),
                'match_native_unit_id' => $this->nullableString('admob_ios_match_native_unit_id'),
            ],
        ];
    }

    private function yasalMetinPayload(string $anahtar, string $baslik): array
    {
        $ayar = Ayar::query()->where('anahtar', $anahtar)->first();

        return [
            'anahtar' => $anahtar,
            'baslik' => $baslik,
            'icerik' => (string) ($ayar?->deger ?? ''),
            'guncellendi_at' => $ayar?->updated_at?->toISOString(),
        ];
    }
}
