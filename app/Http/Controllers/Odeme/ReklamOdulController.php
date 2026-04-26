<?php

namespace App\Http\Controllers\Odeme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Odeme\ReklamOdulKaydetRequest;
use App\Models\ReklamOdulu;
use App\Services\AyarServisi;
use App\Services\PuanServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReklamOdulController extends Controller
{
    public function __construct(
        private PuanServisi $puanServisi,
        private AyarServisi $ayarServisi,
    ) {}

    public function durum(Request $request): JsonResponse
    {
        $bugunIzlenen = $this->bugunIzlenenSayisi($request->user()->id);
        $gunlukLimit = $this->gunlukLimit();

        return response()->json([
            'aktif_mi' => $this->odulAktifMi(),
            'odul_puani' => $this->odulPuani(),
            'gunluk_limit' => $gunlukLimit,
            'bugun_izlenen' => $bugunIzlenen,
            'kalan_hak' => $this->kalanHak($gunlukLimit, $bugunIzlenen),
        ]);
    }

    public function kaydet(ReklamOdulKaydetRequest $request): JsonResponse
    {
        $veri = $request->validated();
        $user = $request->user();
        $gunlukLimit = $this->gunlukLimit();
        $odulPuani = $this->odulPuani();

        if (!$this->odulAktifMi()) {
            return response()->json(['mesaj' => 'Reklam odulleri su anda pasif.'], 422);
        }

        $mevcutOdul = ReklamOdulu::query()
            ->where('user_id', $user->id)
            ->where('olay_kodu', $veri['olay_kodu'])
            ->first();

        if ($mevcutOdul) {
            $bugunIzlenen = $this->bugunIzlenenSayisi($user->id);

            return response()->json([
                'mesaj' => "Odul zaten hesabiniza eklenmis: +{$mevcutOdul->odul_miktari} puan",
                'odul_puani' => (int) $mevcutOdul->odul_miktari,
                'mevcut_puan' => (int) $user->fresh()->mevcut_puan,
                'gunluk_limit' => $gunlukLimit,
                'bugun_izlenen' => $bugunIzlenen,
                'kalan_hak' => $this->kalanHak($gunlukLimit, $bugunIzlenen),
                'olay_kodu' => $mevcutOdul->olay_kodu,
                'tekrar_mi' => true,
            ]);
        }

        $bugunIzlenen = $this->bugunIzlenenSayisi($user->id);

        if ($bugunIzlenen >= $gunlukLimit) {
            return response()->json([
                'mesaj' => 'Gunluk reklam odul limitine ulastiniz.',
                'odul_puani' => $odulPuani,
                'gunluk_limit' => $gunlukLimit,
                'bugun_izlenen' => $bugunIzlenen,
                'kalan_hak' => 0,
            ], 429);
        }

        $odul = DB::transaction(function () use ($user, $veri, $odulPuani) {
            $odul = ReklamOdulu::query()->create([
                'user_id' => $user->id,
                'olay_kodu' => $veri['olay_kodu'],
                'reklam_platformu' => $veri['reklam_platformu'],
                'reklam_birim_kodu' => $veri['reklam_birim_kodu'],
                'reklam_tipi' => $veri['reklam_tipi'] ?? 'rewarded',
                'odul_tipi' => 'puan',
                'odul_miktari' => $odulPuani,
                'dogrulandi_mi' => true,
            ]);

            $this->puanServisi->ekle(
                $user,
                $odulPuani,
                'reklam',
                'Reklam izleme odulu',
                'reklam_odulu',
                $odul->id,
            );

            return $odul;
        });

        $bugunIzlenen++;
        $user->refresh();

        return response()->json([
            'mesaj' => "Odul kazandiniz: +{$odulPuani} puan",
            'odul_puani' => $odulPuani,
            'mevcut_puan' => (int) $user->mevcut_puan,
            'gunluk_limit' => $gunlukLimit,
            'bugun_izlenen' => $bugunIzlenen,
            'kalan_hak' => $this->kalanHak($gunlukLimit, $bugunIzlenen),
            'olay_kodu' => $odul->olay_kodu,
            'tekrar_mi' => false,
        ], 201);
    }

    private function odulAktifMi(): bool
    {
        return (bool) $this->ayarServisi->al('admob_aktif_mi', false)
            && $this->odulPuani() > 0
            && $this->gunlukLimit() > 0;
    }

    private function odulPuani(): int
    {
        return max(0, (int) $this->ayarServisi->al('reklam_odulu', 15));
    }

    private function gunlukLimit(): int
    {
        return max(0, (int) $this->ayarServisi->al('reklam_gunluk_odul_limiti', 10));
    }

    private function bugunIzlenenSayisi(int $userId): int
    {
        return ReklamOdulu::query()
            ->where('user_id', $userId)
            ->where('dogrulandi_mi', true)
            ->whereDate('created_at', today())
            ->count();
    }

    private function kalanHak(int $gunlukLimit, int $bugunIzlenen): int
    {
        return max(0, $gunlukLimit - $bugunIzlenen);
    }
}
