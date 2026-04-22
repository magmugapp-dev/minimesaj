<?php

namespace App\Services;

use App\Events\EslesmeOlustu;
use App\Jobs\EslesmeSonrasiAiIlkMesajGorevi;
use App\Models\Begeni;
use App\Models\Engelleme;
use App\Models\Eslesme;
use App\Models\Sohbet;
use App\Models\User;
use App\Notifications\YeniBegeni;
use App\Notifications\YeniEslesme;
use App\Services\YapayZeka\AiKullaniciHazirlamaServisi;
use App\Services\YapayZeka\AiMesajZamanlamaServisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EslesmeServisi
{
    public function __construct(
        private ?AiKullaniciHazirlamaServisi $aiKullaniciHazirlamaServisi = null,
        private ?AiMesajZamanlamaServisi $aiMesajZamanlamaServisi = null,
        private ?PuanServisi $puanServisi = null,
        private ?AyarServisi $ayarServisi = null,
    ) {
        $this->aiKullaniciHazirlamaServisi ??= app(AiKullaniciHazirlamaServisi::class);
        $this->aiMesajZamanlamaServisi ??= app(AiMesajZamanlamaServisi::class);
        $this->puanServisi ??= app(PuanServisi::class);
        $this->ayarServisi ??= app(AyarServisi::class);
    }

    /**
     * Beğeni oluştur; karşılıklıysa otomatik eşleşme yap.
     */
    public function begen(User $begenen, User $begenilen): array
    {
        $engellendi = Engelleme::where(function ($q) use ($begenen, $begenilen) {
            $q->where('engelleyen_user_id', $begenilen->id)
                ->where('engellenen_user_id', $begenen->id);
        })->orWhere(function ($q) use ($begenen, $begenilen) {
            $q->where('engelleyen_user_id', $begenen->id)
                ->where('engellenen_user_id', $begenilen->id);
        })->exists();

        if ($engellendi) {
            return ['durum' => 'engellendi'];
        }

        if (!$begenilen->cevrim_ici_mi) {
            return ['durum' => 'cevrim_disi'];
        }

        $begeni = Begeni::firstOrCreate(
            ['begenen_user_id' => $begenen->id, 'begenilen_user_id' => $begenilen->id],
        );

        if ($begenilen->hesap_tipi === 'ai') {
            $this->aiKullaniciHazirlamaServisi->hazirla($begenilen);

            Begeni::firstOrCreate(
                ['begenen_user_id' => $begenilen->id, 'begenilen_user_id' => $begenen->id],
            );
        }

        $karsilikli = Begeni::query()
            ->where('begenen_user_id', $begenilen->id)
            ->where('begenilen_user_id', $begenen->id)
            ->where('eslesmeye_donustu_mu', false)
            ->first();

        if ($karsilikli) {
            return DB::transaction(function () use ($begeni, $karsilikli, $begenen, $begenilen) {
                $begeni->update(['eslesmeye_donustu_mu' => true]);
                $karsilikli->update(['eslesmeye_donustu_mu' => true]);

                $eslesme = Eslesme::create([
                    'user_id' => $begenen->id,
                    'eslesen_user_id' => $begenilen->id,
                    'eslesme_turu' => 'otomatik',
                    'eslesme_kaynagi' => $begenilen->hesap_tipi === 'ai'
                        ? 'yapay_zeka'
                        : 'gercek_kullanici',
                    'durum' => 'aktif',
                    'baslatan_user_id' => $begenen->id,
                ]);

                $sohbet = Sohbet::create([
                    'eslesme_id' => $eslesme->id,
                    'durum' => 'aktif',
                ]);

                DB::afterCommit(function () use ($eslesme, $sohbet, $begenen, $begenilen) {
                    EslesmeOlustu::dispatch($eslesme);

                    $begenen->notify(new YeniEslesme($eslesme, $begenilen));
                    $begenilen->notify(new YeniEslesme($eslesme, $begenen));

                    $aiUser = $this->ilkMesajiAtacakAiBul($begenen, $begenilen);

                    if ($aiUser?->aiAyar?->ilk_mesaj_atar_mi) {
                        $zamanlama = $this->aiMesajZamanlamaServisi
                            ->ilkMesajDurumu($sohbet, $aiUser);

                        if ($zamanlama['hemen_calistir'] && app()->environment('local')) {
                            EslesmeSonrasiAiIlkMesajGorevi::dispatchSync($sohbet, $aiUser);

                            return;
                        }

                        $gorev = EslesmeSonrasiAiIlkMesajGorevi::dispatch($sohbet, $aiUser);

                        if ($zamanlama['sonraki_kontrol_at']) {
                            $gorev->delay($zamanlama['sonraki_kontrol_at']);
                        }
                    }
                });

                return ['durum' => 'eslesme', 'eslesme_id' => $eslesme->id];
            });
        }

        $begenilen->notify(new YeniBegeni($begenen));

        return ['durum' => 'begenildi'];
    }

    public function merkez(User $user): array
    {
        $adaySorgusu = $this->adaySorgusu($user);

        return [
            'mevcut_puan' => (int) $user->mevcut_puan,
            'eslesme_baslatma_maliyeti' => $this->eslesmeBaslatmaMaliyeti(),
            'cevrimici_kisi_sayisi' => (clone $adaySorgusu)->count(),
            'bekleyen_begeni_sayisi' => $this->bekleyenBegeniSayisi($user),
            'filtreler' => $this->filtreleriDiziyeDonustur($user),
        ];
    }

    public function tercihleriGuncelle(User $user, array $veri): User
    {
        $user->fill([
            'eslesme_cinsiyet_filtresi' => $veri['cinsiyet'] ?? $user->eslesme_cinsiyet_filtresi,
            'eslesme_yas_filtresi' => $veri['yas'] ?? $user->eslesme_yas_filtresi,
            'super_eslesme_aktif_mi' => $veri['super_eslesme_aktif_mi'] ?? $user->super_eslesme_aktif_mi,
        ]);

        $user->save();

        return $user->fresh();
    }

    public function eslesmeBaslat(User $user): array
    {
        $user = $user->fresh();
        $aday = $this->sonrakiAday($user);
        $maliyet = $this->eslesmeBaslatmaMaliyeti();

        if (!$aday) {
            return [
                'durum' => 'aday_yok',
                'mevcut_puan' => (int) $user->mevcut_puan,
                'eslesme_baslatma_maliyeti' => $maliyet,
            ];
        }

        if ($user->mevcut_puan < $maliyet) {
            return [
                'durum' => 'yetersiz_puan',
                'mevcut_puan' => (int) $user->mevcut_puan,
                'gerekli_puan' => $maliyet,
                'eksik_puan' => $maliyet - (int) $user->mevcut_puan,
            ];
        }

        $this->puanServisi->harca(
            $user,
            $maliyet,
            'Eşleşme başlatma',
            'user',
            $aday->id,
        );

        return [
            'durum' => 'aday_bulundu',
            'aday' => $aday->fresh('fotograflar'),
            'mevcut_puan' => (int) $user->fresh()->mevcut_puan,
            'eslesme_baslatma_maliyeti' => $maliyet,
        ];
    }

    /**
     * Rastgele eşleşme (queue job'i tarafından tetiklenir).
     */
    public function rastgeleEslestir(User $user): ?Eslesme
    {
        $eslesen = $this->adaySorgusu($user)
            ->inRandomOrder()
            ->first();

        if (!$eslesen) {
            return null;
        }

        if ($eslesen->hesap_tipi === 'ai') {
            $this->aiKullaniciHazirlamaServisi->hazirla($eslesen);
        }

        return DB::transaction(function () use ($user, $eslesen) {
            $eslesme = Eslesme::create([
                'user_id' => $user->id,
                'eslesen_user_id' => $eslesen->id,
                'eslesme_turu' => 'rastgele',
                'eslesme_kaynagi' => $eslesen->hesap_tipi === 'ai' ? 'yapay_zeka' : 'gercek_kullanici',
                'durum' => 'aktif',
                'baslatan_user_id' => $user->id,
            ]);

            $sohbet = Sohbet::create([
                'eslesme_id' => $eslesme->id,
                'durum' => 'aktif',
            ]);

            DB::afterCommit(function () use ($eslesme, $sohbet, $user, $eslesen) {
                EslesmeOlustu::dispatch($eslesme);

                $aiUser = $this->ilkMesajiAtacakAiBul($user, $eslesen);

                if ($aiUser?->aiAyar?->ilk_mesaj_atar_mi) {
                    $zamanlama = $this->aiMesajZamanlamaServisi
                        ->ilkMesajDurumu($sohbet, $aiUser);

                    if ($zamanlama['hemen_calistir'] && app()->environment('local')) {
                        EslesmeSonrasiAiIlkMesajGorevi::dispatchSync($sohbet, $aiUser);

                        return;
                    }

                    $gorev = EslesmeSonrasiAiIlkMesajGorevi::dispatch($sohbet, $aiUser);

                    if ($zamanlama['sonraki_kontrol_at']) {
                        $gorev->delay($zamanlama['sonraki_kontrol_at']);
                    }
                }
            });

            return $eslesme;
        });
    }

    public function bitir(Eslesme $eslesme, User $user, string $sebep = 'kullanici_istegi'): void
    {
        $eslesme->update([
            'durum' => 'bitti',
            'bitis_sebebi' => $sebep,
        ]);

        if ($sohbet = $eslesme->sohbet) {
            $sohbet->update(['durum' => 'kapandi']);
        }
    }

    private function ilkMesajiAtacakAiBul(User $birinci, User $ikinci): ?User
    {
        $aday = $birinci->hesap_tipi === 'ai'
            ? $birinci
            : ($ikinci->hesap_tipi === 'ai' ? $ikinci : null);

        if (!$aday) {
            return null;
        }

        $this->aiKullaniciHazirlamaServisi->hazirla($aday);

        return $aday->fresh('aiAyar');
    }

    private function sonrakiAday(User $user): ?User
    {
        $sorgu = $this->adaySorgusu($user)->with('fotograflar');

        if ($user->super_eslesme_aktif_mi) {
            return $sorgu->inRandomOrder()
                ->limit(40)
                ->get()
                ->sortByDesc(fn (User $aday) => $this->uyumlulukPuani($user, $aday))
                ->first();
        }

        return $sorgu->inRandomOrder()->first();
    }

    private function adaySorgusu(User $user): Builder
    {
        $haricTutulanlar = $this->haricTutulanKullaniciIdleri($user);

        $sorgu = User::query()
            ->whereIn('hesap_tipi', ['user', 'ai'])
            ->where('hesap_durumu', 'aktif')
            ->where('cevrim_ici_mi', true)
            ->whereNotIn('id', $haricTutulanlar);

        $this->cinsiyetFiltresiUygula($sorgu, $user);
        $this->yasFiltresiUygula($sorgu, $user);

        return $sorgu->orderByRaw("CASE WHEN hesap_tipi = 'user' THEN 0 ELSE 1 END");
    }

    private function haricTutulanKullaniciIdleri(User $user): Collection
    {
        $engellenen = Engelleme::query()
            ->where('engelleyen_user_id', $user->id)
            ->pluck('engellenen_user_id')
            ->merge(
                Engelleme::query()
                    ->where('engellenen_user_id', $user->id)
                    ->pluck('engelleyen_user_id')
            );

        $begenilen = Begeni::query()
            ->where('begenen_user_id', $user->id)
            ->pluck('begenilen_user_id');

        $eslesilen = Eslesme::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('eslesen_user_id', $user->id);
            })
            ->where('durum', 'aktif')
            ->get(['user_id', 'eslesen_user_id'])
            ->flatMap(function (Eslesme $eslesme) use ($user) {
                return collect([$eslesme->user_id, $eslesme->eslesen_user_id])
                    ->reject(fn ($id) => (int) $id === (int) $user->id);
            });

        return $engellenen
            ->merge($begenilen)
            ->merge($eslesilen)
            ->push($user->id)
            ->unique()
            ->values();
    }

    private function cinsiyetFiltresiUygula(Builder $sorgu, User $user): void
    {
        match ($user->eslesme_cinsiyet_filtresi) {
            'kadin' => $sorgu->where('cinsiyet', 'kadin'),
            'erkek' => $sorgu->where('cinsiyet', 'erkek'),
            default => null,
        };
    }

    private function yasFiltresiUygula(Builder $sorgu, User $user): void
    {
        $simdikiYil = (int) now()->year;

        match ($user->eslesme_yas_filtresi) {
            '18_25' => $sorgu->whereBetween('dogum_yili', [$simdikiYil - 25, $simdikiYil - 18]),
            '26_35' => $sorgu->whereBetween('dogum_yili', [$simdikiYil - 35, $simdikiYil - 26]),
            '36_ustu' => $sorgu->where('dogum_yili', '<=', $simdikiYil - 36),
            default => null,
        };
    }

    private function bekleyenBegeniSayisi(User $user): int
    {
        return $user->gelenBegeniler()
            ->where('eslesmeye_donustu_mu', false)
            ->count();
    }

    private function filtreleriDiziyeDonustur(User $user): array
    {
        return [
            'cinsiyet' => $user->eslesme_cinsiyet_filtresi ?? 'tum',
            'yas' => $user->eslesme_yas_filtresi ?? 'tum',
            'super_eslesme_aktif_mi' => (bool) $user->super_eslesme_aktif_mi,
        ];
    }

    private function eslesmeBaslatmaMaliyeti(): int
    {
        return max(1, (int) $this->ayarServisi->al('eslesme_baslatma_maliyeti', 8));
    }

    private function uyumlulukPuani(User $user, User $aday): int
    {
        $puan = 0;

        if ($user->il && $aday->il && mb_strtolower($user->il) === mb_strtolower($aday->il)) {
            $puan += 35;
        }

        if ($user->ulke && $aday->ulke && mb_strtolower($user->ulke) === mb_strtolower($aday->ulke)) {
            $puan += 20;
        }

        if ($user->dogum_yili && $aday->dogum_yili) {
            $yasFarki = abs((int) $user->dogum_yili - (int) $aday->dogum_yili);

            if ($yasFarki <= 2) {
                $puan += 25;
            } elseif ($yasFarki <= 5) {
                $puan += 15;
            } elseif ($yasFarki <= 8) {
                $puan += 8;
            }
        }

        if ($aday->hesap_tipi === 'user') {
            $puan += 10;
        }

        if (($aday->relationLoaded('fotograflar') && $aday->fotograflar->isNotEmpty()) || filled($aday->profil_resmi)) {
            $puan += 10;
        }

        return $puan;
    }
}
