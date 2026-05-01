<?php

namespace App\Http\Controllers\Kimlik;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kimlik\KullaniciAdiMusaitRequest;
use App\Http\Requests\Kimlik\SosyalGirisRequest;
use App\Http\Requests\Kimlik\SosyalKayitRequest;
use App\Http\Resources\KullaniciResource;
use App\Services\AyarServisi;
use App\Services\Kimlik\DeviceBindingService;
use App\Services\Kimlik\Sosyal\SosyalAuthServisi;
use Illuminate\Http\JsonResponse;

class SosyalKimlikController extends Controller
{
    public function __construct(
        private SosyalAuthServisi $sosyalAuthServisi,
        private AyarServisi $ayarServisi,
        private DeviceBindingService $deviceBindingService,
    ) {}

    public function giris(SosyalGirisRequest $request): JsonResponse
    {
        if ($yanit = $this->guncellemeGerekliYaniti($request->validated('uygulama_versiyonu'))) {
            return $yanit;
        }

        $veri = $request->validated();
        $sonuc = $this->sosyalAuthServisi->giris($veri);

        if ($sonuc['durum'] === 'authenticated') {
            $this->deviceBindingService->bindOrFail(
                $veri['device_fingerprint'] ?? null,
                $sonuc['user'],
                $veri['platform'] ?? $veri['istemci_tipi'] ?? null,
            );

            return response()->json([
                'durum' => 'authenticated',
                'kullanici' => new KullaniciResource($sonuc['user']),
                'token' => $sonuc['token'],
            ]);
        }

        return response()->json([
            'durum' => 'onboarding_required',
            'social_session' => $sonuc['social_session'],
            'prefill' => $sonuc['prefill'],
        ]);
    }

    public function kayit(SosyalKayitRequest $request): JsonResponse
    {
        if ($yanit = $this->guncellemeGerekliYaniti($request->validated('uygulama_versiyonu'))) {
            return $yanit;
        }

        $veri = $request->validated();
        $this->deviceBindingService->ensureAvailableForRegistration($veri['device_fingerprint'] ?? null);

        $sonuc = $this->sosyalAuthServisi->kayit(
            $veri,
            $request->file('dosya'),
        );
        $this->deviceBindingService->bindOrFail(
            $veri['device_fingerprint'] ?? null,
            $sonuc['user'],
            $veri['platform'] ?? 'social',
        );

        return response()->json([
            'durum' => 'authenticated',
            'kullanici' => new KullaniciResource($sonuc['user']),
            'token' => $sonuc['token'],
        ], $sonuc['status']);
    }

    public function kullaniciAdiMusait(KullaniciAdiMusaitRequest $request): JsonResponse
    {
        return response()->json([
            'musait' => $this->sosyalAuthServisi->kullaniciAdiMusait(
                $request->validated('kullanici_adi'),
            ),
        ]);
    }

    private function guncellemeGerekliYaniti(?string $uygulamaVersiyonu): ?JsonResponse
    {
        $minimumVersiyon = $this->normalizeVersion(
            $this->ayarServisi->al('mobil_minimum_versiyon', env('MOBIL_MINIMUM_VERSIYON')),
        );
        $istemciVersiyonu = $this->normalizeVersion($uygulamaVersiyonu);

        if ($minimumVersiyon === null || $istemciVersiyonu === null) {
            return null;
        }

        if (version_compare($istemciVersiyonu, $minimumVersiyon, '>=')) {
            return null;
        }

        $guncellemeUrl = $this->normalizeString(
            $this->ayarServisi->al(
                'android_play_store_url',
                env('ANDROID_PLAY_STORE_URL'),
            ),
        );

        return response()->json([
            'kod' => 'update_required',
            'mesaj' => 'Devam etmek icin uygulamanin guncel surumunu yuklemen gerekiyor.',
            'minimum_versiyon' => $minimumVersiyon,
            'guncelleme_url' => $guncellemeUrl,
        ], 426);
    }

    private function normalizeVersion(mixed $version): ?string
    {
        $normalized = $this->normalizeString($version);
        if ($normalized === null) {
            return null;
        }

        $normalized = preg_replace('/\+.*/', '', $normalized);
        $normalized = trim((string) $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
