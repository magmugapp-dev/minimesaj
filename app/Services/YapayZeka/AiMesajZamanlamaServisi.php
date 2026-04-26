<?php

namespace App\Services\YapayZeka;

use App\Models\AiAyar;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\Users\UserOnlineStatusService;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;

class AiMesajZamanlamaServisi
{
    public function __construct(
        private ?UserOnlineStatusService $userOnlineStatusService = null,
    ) {
        $this->userOnlineStatusService ??= app(UserOnlineStatusService::class);
    }

    public function sohbetCevabiDurumu(
        Mesaj $gelenMesaj,
        User $aiUser,
        ?CarbonInterface $simdi = null,
    ): array {
        $referansAn = $gelenMesaj->created_at instanceof CarbonInterface
            ? Carbon::instance($gelenMesaj->created_at)
            : now();

        return $this->durumOlustur(
            $referansAn,
            "{$gelenMesaj->id}|{$aiUser->id}|dating-reply",
            $aiUser,
            $gelenMesaj->sohbet,
            $simdi,
            false,
        );
    }

    public function ilkMesajDurumu(
        Sohbet $sohbet,
        User $aiUser,
        ?CarbonInterface $simdi = null,
    ): array {
        $referansAn = $sohbet->created_at instanceof CarbonInterface
            ? Carbon::instance($sohbet->created_at)
            : now();

        return $this->durumOlustur(
            $referansAn,
            "{$sohbet->id}|{$aiUser->id}|dating-first-message",
            $aiUser,
            $sohbet,
            $simdi,
            false,
        );
    }

    private function durumOlustur(
        CarbonInterface $referansAn,
        string $tohum,
        User $aiUser,
        ?Sohbet $sohbet = null,
        ?CarbonInterface $simdi = null,
        bool $dakikaGecikmesiniDahilEt = false,
    ): array {
        $ayar = $aiUser->aiAyar;
        $simdi = $simdi ? Carbon::instance($simdi) : now();

        if (!$ayar || !$ayar->aktif_mi) {
            return [
                'hemen_calistir' => false,
                'bekleme_nedeni' => 'ai_pasif',
                'planlanan_at' => Carbon::instance($referansAn),
                'sonraki_kontrol_at' => null,
                'aktif_saatte_mi' => false,
                'cevrim_ici_mi' => false,
            ];
        }

        $planlananAt = $this->planlananZamaniHesapla(
            $referansAn,
            $ayar,
            $tohum,
            $dakikaGecikmesiniDahilEt,
        );
        $onlineState = $this->userOnlineStatusService->resolve($aiUser, $simdi);
        $this->userOnlineStatusService->syncResolvedStatus($aiUser, $onlineState, $simdi);
        $aktifSaatteMi = (bool) ($onlineState['active_time'] ?? $onlineState['is_online']);
        $cevrimIciMi = (bool) $onlineState['is_online'];
        $sessizModBitisAt = $sohbet?->ai_sessiz_mod_bitis_at
            ? Carbon::instance($sohbet->ai_sessiz_mod_bitis_at)
            : null;

        $beklemeNedeni = null;
        $sonrakiKontrolAt = null;

        if (!$aktifSaatteMi) {
            $beklemeNedeni = 'aktif_saat_disinda';
            $sonrakiKontrolAt = $this->enGecTarihiBul(
                $planlananAt,
                $onlineState['next_active_at'] ?? $this->sonrakiAktifAn($ayar, $simdi),
            );
        } elseif (!$cevrimIciMi) {
            $beklemeNedeni = 'cevrim_disi';
            $sonrakiKontrolAt = $this->enGecTarihiBul(
                $planlananAt,
                $simdi->copy()->addMinutes(5),
            );
        } elseif ($sessizModBitisAt && $simdi->lt($sessizModBitisAt)) {
            $beklemeNedeni = 'sohbet_sessizde';
            $sonrakiKontrolAt = $this->enGecTarihiBul(
                $planlananAt,
                $sessizModBitisAt,
            );
        } elseif ($simdi->lt($planlananAt)) {
            $beklemeNedeni = 'cevap_akisi';
            $sonrakiKontrolAt = Carbon::instance($planlananAt);
        }

        return [
            'hemen_calistir' => $beklemeNedeni === null,
            'bekleme_nedeni' => $beklemeNedeni,
            'planlanan_at' => $planlananAt,
            'sonraki_kontrol_at' => $sonrakiKontrolAt,
            'aktif_saatte_mi' => $aktifSaatteMi,
            'cevrim_ici_mi' => $cevrimIciMi,
            'sessiz_mod_bitis_at' => $sessizModBitisAt,
        ];
    }

    public function sessizlikPenceresiBaslat(
        Sohbet $sohbet,
        User $aiUser,
        Mesaj $aiMesaji,
    ): ?Carbon {
        $maksimumDakika = max(0, (int) ($aiUser->aiAyar?->rastgele_gecikme_dakika ?? 0));

        if ($maksimumDakika <= 0) {
            $this->sessizlikPenceresiniTemizle($sohbet);

            return null;
        }

        $bitisAt = now()->addSeconds(random_int(0, $maksimumDakika * 60));

        $sohbet->forceFill([
            'ai_sessiz_mod_bitis_at' => $bitisAt,
            'ai_sessiz_mod_tetikleyen_mesaj_id' => $aiMesaji->id,
        ])->save();

        return $bitisAt;
    }

    public function sessizlikPenceresiniTemizle(Sohbet $sohbet): void
    {
        if (!$sohbet->ai_sessiz_mod_bitis_at && !$sohbet->ai_sessiz_mod_tetikleyen_mesaj_id) {
            return;
        }

        $sohbet->forceFill([
            'ai_sessiz_mod_bitis_at' => null,
            'ai_sessiz_mod_tetikleyen_mesaj_id' => null,
        ])->save();
    }

    private function planlananZamaniHesapla(
        CarbonInterface $referansAn,
        AiAyar $ayar,
        string $tohum,
        bool $dakikaGecikmesiniDahilEt = false,
    ): Carbon {
        $minimum = max(0, (int) ($ayar->minimum_cevap_suresi_saniye ?? 0));
        $maksimum = max($minimum, (int) ($ayar->maksimum_cevap_suresi_saniye ?? $minimum));
        $rastgeleDakika = $dakikaGecikmesiniDahilEt
            ? max(0, (int) ($ayar->rastgele_gecikme_dakika ?? 0))
            : 0;
        $seed = abs((int) crc32($tohum));

        $cevapSuresi = $minimum;
        if ($maksimum > $minimum) {
            $cevapSuresi += $seed % (($maksimum - $minimum) + 1);
        }

        $ekGecikme = 0;
        if ($rastgeleDakika > 0) {
            $ekGecikme = intdiv($seed, 97) % (($rastgeleDakika * 60) + 1);
        }

        return Carbon::instance($referansAn)->addSeconds($cevapSuresi + $ekGecikme);
    }

    private function sonrakiAktifAn(AiAyar $ayar, CarbonInterface $an): ?Carbon
    {
        $uykuAraligi = $this->icindeOlduguUykuAraligi($ayar, $an);

        return $uykuAraligi['bitis'] ?? null;
    }

    private function icindeOlduguUykuAraligi(AiAyar $ayar, CarbonInterface $an): ?array
    {
        $yerelAn = $this->yerelAnaCevir($ayar, $an);

        foreach ([$yerelAn->copy()->subDay(), $yerelAn->copy()] as $gun) {
            [$baslangicSaati, $bitisSaati] = $this->uykuSaatleriniSec($ayar, $gun);

            if (!$baslangicSaati || !$bitisSaati) {
                continue;
            }

            $baslangic = $gun->copy()->setTimeFromTimeString($baslangicSaati);
            $bitis = $gun->copy()->setTimeFromTimeString($bitisSaati);

            if ($bitis->lessThanOrEqualTo($baslangic)) {
                $bitis->addDay();
            }

            if ($yerelAn->greaterThanOrEqualTo($baslangic) && $yerelAn->lessThan($bitis)) {
                return [
                    'baslangic' => $baslangic->setTimezone(config('app.timezone')),
                    'bitis' => $bitis->setTimezone(config('app.timezone')),
                ];
            }
        }

        return null;
    }

    private function uykuSaatleriniSec(AiAyar $ayar, CarbonInterface $gun): array
    {
        $haftaSonu = $gun->isWeekend();
        $baslangic = $haftaSonu
            ? ($ayar->hafta_sonu_uyku_baslangic ?: $ayar->uyku_baslangic)
            : $ayar->uyku_baslangic;
        $bitis = $haftaSonu
            ? ($ayar->hafta_sonu_uyku_bitis ?: $ayar->uyku_bitis)
            : $ayar->uyku_bitis;

        return [$baslangic, $bitis];
    }

    private function yerelAnaCevir(AiAyar $ayar, CarbonInterface $an): Carbon
    {
        $saatDilimi = $ayar->saat_dilimi ?: config('app.timezone');

        try {
            $gecerliSaatDilimi = new DateTimeZone($saatDilimi);
        } catch (\Throwable) {
            $gecerliSaatDilimi = new DateTimeZone(config('app.timezone'));
        }

        return Carbon::instance($an)->setTimezone($gecerliSaatDilimi);
    }

    private function enGecTarihiBul(?CarbonInterface ...$tarihler): ?Carbon
    {
        $gecerliTarihler = collect($tarihler)
            ->filter(fn($tarih) => $tarih instanceof CarbonInterface)
            ->map(fn(CarbonInterface $tarih) => Carbon::instance($tarih));

        if ($gecerliTarihler->isEmpty()) {
            return null;
        }

        /** @var Carbon $maksimum */
        $maksimum = $gecerliTarihler->shift();

        foreach ($gecerliTarihler as $tarih) {
            if ($tarih->greaterThan($maksimum)) {
                $maksimum = $tarih;
            }
        }

        return $maksimum;
    }
}
