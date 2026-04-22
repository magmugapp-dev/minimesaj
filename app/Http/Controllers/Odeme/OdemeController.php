<?php

namespace App\Http\Controllers\Odeme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Odeme\OdemeDogrulaRequest;
use App\Models\AbonelikPaketi;
use App\Models\Odeme;
use App\Models\PuanPaketi;
use App\Models\User;
use App\Services\FisDogrulamaServisi;
use App\Services\PuanServisi;
use Illuminate\Http\JsonResponse;

class OdemeController extends Controller
{
    public function __construct(
        private PuanServisi $puanServisi,
        private FisDogrulamaServisi $fisDogrulamaServisi,
    ) {}

    public function dogrula(OdemeDogrulaRequest $request): JsonResponse
    {
        $veri = $request->validated();
        $urunTipi = $veri['urun_tipi'] ?? 'tek_seferlik';
        $paket = $this->paketBul($veri['urun_kodu'], $veri['platform'], $urunTipi);

        if (!$paket) {
            return response()->json([
                'mesaj' => $urunTipi === 'abonelik'
                    ? 'Aktif abonelik paketi bulunamadi.'
                    : 'Aktif puan paketi bulunamadi.',
            ], 422);
        }

        $dogrulamaSonucu = $this->fisDogrulamaServisi->dogrula(
            $veri['platform'],
            $veri['fis_verisi'],
            $veri['urun_kodu'],
            $urunTipi,
        );

        if (!$dogrulamaSonucu['gecerli']) {
            Odeme::create([
                'user_id' => $request->user()->id,
                'platform' => $veri['platform'],
                'magaza_tipi' => Odeme::platformMagazaTipi($veri['platform']),
                'urun_kodu' => $veri['urun_kodu'],
                'urun_tipi' => $urunTipi,
                'islem_kodu' => $dogrulamaSonucu['islem_kodu'],
                'tutar' => $veri['tutar'],
                'para_birimi' => $veri['para_birimi'],
                'durum' => 'basarisiz',
                'dogrulama_durumu' => 'reddedildi',
            ]);

            return response()->json([
                'mesaj' => $dogrulamaSonucu['hata'] ?? 'Fis dogrulanamadi.',
            ], 422);
        }

        if ($dogrulamaSonucu['islem_kodu']) {
            $tekrarKontrol = Odeme::where('islem_kodu', $dogrulamaSonucu['islem_kodu'])
                ->where('durum', 'basarili')
                ->first();

            if ($tekrarKontrol) {
                return response()->json([
                    'mesaj' => 'Bu fis zaten islenmis.',
                    'odeme' => $tekrarKontrol,
                ], 200);
            }
        }

        $odeme = Odeme::create([
            'user_id' => $request->user()->id,
            'platform' => $veri['platform'],
            'magaza_tipi' => Odeme::platformMagazaTipi($veri['platform']),
            'urun_kodu' => $veri['urun_kodu'],
            'urun_tipi' => $urunTipi,
            'islem_kodu' => $dogrulamaSonucu['islem_kodu'],
            'tutar' => $veri['tutar'],
            'para_birimi' => $veri['para_birimi'],
            'durum' => 'basarili',
            'dogrulama_durumu' => 'dogrulandi',
        ]);

        if ($urunTipi === 'abonelik' && $paket instanceof AbonelikPaketi) {
            $this->abonelikAktiflestir($request->user(), $paket);
        } elseif ($paket instanceof PuanPaketi) {
            $this->puanServisi->ekle(
                $request->user(),
                $paket->puan,
                'odeme',
                "Satin alma: {$paket->kod}",
                'odeme',
                $odeme->id,
            );
        }

        return response()->json([
            'mesaj' => $urunTipi === 'abonelik' ? 'Abonelik aktiflestirildi.' : 'Odeme onaylandi.',
            'odeme' => $odeme,
            'paket' => [
                'kod' => $paket->kod,
                'urun_tipi' => $urunTipi,
                'puan' => $paket instanceof PuanPaketi ? $paket->puan : 0,
                'sure_ay' => $paket instanceof AbonelikPaketi ? $paket->sure_ay : null,
            ],
        ], 201);
    }

    private function paketBul(string $urunKodu, string $platform, string $urunTipi): PuanPaketi|AbonelikPaketi|null
    {
        $sutun = $platform === 'ios' ? 'ios_urun_kodu' : 'android_urun_kodu';

        if ($urunTipi === 'abonelik') {
            return AbonelikPaketi::query()
                ->aktif()
                ->where(function ($query) use ($sutun, $urunKodu) {
                    $query->where($sutun, $urunKodu)
                        ->orWhere('kod', $urunKodu);
                })
                ->first();
        }

        return PuanPaketi::query()
            ->aktif()
            ->where(function ($query) use ($sutun, $urunKodu) {
                $query->where($sutun, $urunKodu)
                    ->orWhere('kod', $urunKodu);
            })
            ->first();
    }

    private function abonelikAktiflestir(User $user, AbonelikPaketi $paket): void
    {
        $bazTarih = $user->premium_bitis_tarihi && $user->premium_bitis_tarihi->isFuture()
            ? $user->premium_bitis_tarihi
            : now();

        $user->forceFill([
            'premium_aktif_mi' => true,
            'premium_bitis_tarihi' => $bazTarih->copy()->addMonths($paket->sure_ay),
        ])->save();
    }
}
