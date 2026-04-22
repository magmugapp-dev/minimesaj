<?php

namespace App\Services;

use App\Events\EslesmeOlustu;
use App\Jobs\EslesmeSonrasiAiIlkMesajGorevi;
use App\Models\Engelleme;
use App\Models\Eslesme;
use App\Models\EslesmeGecilenKullanici;
use App\Models\Sohbet;
use App\Models\User;
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

    public function sohbetBaslat(User $baslatan, User $aday): array
    {
        if ((int) $baslatan->id === (int) $aday->id) {
            return ['durum' => 'aday_gecersiz'];
        }

        if (!in_array($aday->hesap_tipi, ['user', 'ai'], true) || $aday->is_admin || $aday->hesap_durumu !== 'aktif') {
            return ['durum' => 'aday_gecersiz'];
        }

        if ($this->engellemeVarMi($baslatan, $aday)) {
            return ['durum' => 'engellendi'];
        }

        if ($mevcutEslesme = $this->aktifEslesmeBul($baslatan, $aday)) {
            $sohbet = Sohbet::firstOrCreate(
                ['eslesme_id' => $mevcutEslesme->id],
                ['durum' => 'aktif'],
            );
            if ($sohbet->durum !== 'aktif') {
                $sohbet->update(['durum' => 'aktif']);
            }

            return [
                'durum' => 'eslesme',
                'eslesme_id' => $mevcutEslesme->id,
                'sohbet_id' => $sohbet->id,
                'eslesme' => $mevcutEslesme->loadMissing(['user', 'eslesenUser']),
                'sohbet' => $sohbet->loadMissing(['eslesme.user', 'eslesme.eslesenUser', 'sonMesaj.gonderen']),
            ];
        }

        if ($aday->hesap_tipi === 'ai') {
            $this->aiKullaniciHazirlamaServisi->hazirla($aday);
        }

        return DB::transaction(function () use ($baslatan, $aday) {
            $eslesme = Eslesme::create([
                'user_id' => $baslatan->id,
                'eslesen_user_id' => $aday->id,
                'eslesme_turu' => 'otomatik',
                'eslesme_kaynagi' => $aday->hesap_tipi === 'ai'
                    ? 'yapay_zeka'
                    : 'gercek_kullanici',
                'durum' => 'aktif',
                'baslatan_user_id' => $baslatan->id,
            ]);

            $sohbet = Sohbet::create([
                'eslesme_id' => $eslesme->id,
                'durum' => 'aktif',
            ]);

            DB::afterCommit(function () use ($eslesme, $sohbet, $baslatan, $aday) {
                EslesmeOlustu::dispatch($eslesme);

                $baslatan->notify(new YeniEslesme($eslesme, $aday));
                $aday->notify(new YeniEslesme($eslesme, $baslatan));

                try {
                    $aiUser = $this->ilkMesajiAtacakAiBul($baslatan, $aday);

                    if ($aiUser?->aiAyar?->ilk_mesaj_atar_mi) {
                        $zamanlama = $this->aiMesajZamanlamaServisi
                            ->ilkMesajDurumu($sohbet, $aiUser);

                        if ($zamanlama['hemen_calistir'] && app()->environment('local')) {
                            EslesmeSonrasiAiIlkMesajGorevi::dispatchSync($sohbet, $aiUser);

                            return;
                        }

                        if ($zamanlama['sonraki_kontrol_at']) {
                            EslesmeSonrasiAiIlkMesajGorevi::dispatch($sohbet, $aiUser)
                                ->delay($zamanlama['sonraki_kontrol_at']);

                            return;
                        }

                        EslesmeSonrasiAiIlkMesajGorevi::dispatch($sohbet, $aiUser);
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

            return [
                'durum' => 'eslesme',
                'eslesme_id' => $eslesme->id,
                'sohbet_id' => $sohbet->id,
                'eslesme' => $eslesme->loadMissing(['user', 'eslesenUser']),
                'sohbet' => $sohbet->loadMissing(['eslesme.user', 'eslesme.eslesenUser', 'sonMesaj.gonderen']),
            ];
        });
    }

    public function merkez(User $user): array
    {
        $user = $this->gunlukHaklariYenile($user->fresh());
        $adaySorgusu = $this->adaySorgusu($user);

        return [
            'mevcut_puan' => (int) $user->mevcut_puan,
            'gunluk_ucretsiz_hak' => (int) $user->gunluk_ucretsiz_hak,
            'eslesme_baslatma_maliyeti' => $this->eslesmeBaslatmaMaliyeti(),
            'cevrimici_kisi_sayisi' => (clone $adaySorgusu)->where('cevrim_ici_mi', true)->count(),
            'bekleyen_kisi_sayisi' => (clone $adaySorgusu)->count(),
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

    public function adayGec(User $user, User $aday): void
    {
        if ((int) $user->id === (int) $aday->id) {
            return;
        }

        EslesmeGecilenKullanici::query()->firstOrCreate([
            'gecen_user_id' => $user->id,
            'gecilen_user_id' => $aday->id,
        ]);
    }

    public function eslesmeBaslat(User $user): array
    {
        $user = $this->gunlukHaklariYenile($user->fresh());
        $aday = $this->sonrakiAday($user);
        $maliyet = $this->eslesmeBaslatmaMaliyeti();
        $ucretsizHak = max(0, (int) $user->gunluk_ucretsiz_hak);

        if (!$aday) {
            return [
                'durum' => 'aday_yok',
                'mevcut_puan' => (int) $user->mevcut_puan,
                'gunluk_ucretsiz_hak' => $ucretsizHak,
                'eslesme_baslatma_maliyeti' => $maliyet,
            ];
        }

        if ($ucretsizHak <= 0 && $user->mevcut_puan < $maliyet) {
            return [
                'durum' => 'yetersiz_puan',
                'mevcut_puan' => (int) $user->mevcut_puan,
                'gunluk_ucretsiz_hak' => $ucretsizHak,
                'gerekli_puan' => $maliyet,
                'eksik_puan' => $maliyet - (int) $user->mevcut_puan,
            ];
        }

        $ucretsizKullanildi = false;

        if ($ucretsizHak > 0) {
            $user->decrement('gunluk_ucretsiz_hak');
            $ucretsizKullanildi = true;
        } else {
            $this->puanServisi->harca(
                $user,
                $maliyet,
                'Eşleşme başlatma',
                'user',
                $aday->id,
            );
        }

        $yenilenmisKullanici = $user->fresh();

        return [
            'durum' => 'aday_bulundu',
            'aday' => $aday->fresh('fotograflar'),
            'mevcut_puan' => (int) $yenilenmisKullanici->mevcut_puan,
            'gunluk_ucretsiz_hak' => (int) $yenilenmisKullanici->gunluk_ucretsiz_hak,
            'eslesme_baslatma_maliyeti' => $maliyet,
            'ucretsiz_hak_kullanildi' => $ucretsizKullanildi,
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

    private function engellemeVarMi(User $birinci, User $ikinci): bool
    {
        return Engelleme::where(function ($q) use ($birinci, $ikinci) {
            $q->where('engelleyen_user_id', $birinci->id)
                ->where('engellenen_user_id', $ikinci->id);
        })->orWhere(function ($q) use ($birinci, $ikinci) {
            $q->where('engelleyen_user_id', $ikinci->id)
                ->where('engellenen_user_id', $birinci->id);
        })->exists();
    }

    private function aktifEslesmeBul(User $birinci, User $ikinci): ?Eslesme
    {
        return Eslesme::query()
            ->where('durum', 'aktif')
            ->where(function (Builder $query) use ($birinci, $ikinci) {
                $query->where(function (Builder $query) use ($birinci, $ikinci) {
                    $query->where('user_id', $birinci->id)
                        ->where('eslesen_user_id', $ikinci->id);
                })->orWhere(function (Builder $query) use ($birinci, $ikinci) {
                    $query->where('user_id', $ikinci->id)
                        ->where('eslesen_user_id', $birinci->id);
                });
            })
            ->with('sohbet')
            ->first();
    }

    private function sonrakiAday(User $user): ?User
    {
        $sorgu = $this->oncelikliAdaySorgusu($user)->with('fotograflar');

        if ($user->super_eslesme_aktif_mi) {
            return $sorgu->inRandomOrder()
                ->limit(40)
                ->get()
                ->sortByDesc(fn(User $aday) => $this->uyumlulukPuani($user, $aday))
                ->first();
        }

        return $sorgu->inRandomOrder()->first();
    }

    private function oncelikliAdaySorgusu(User $user): Builder
    {
        $cevrimiciSorgu = $this->adaySorgusu($user)
            ->where('cevrim_ici_mi', true);

        if ((clone $cevrimiciSorgu)->exists()) {
            return $cevrimiciSorgu;
        }

        return $this->adaySorgusu($user);
    }

    private function adaySorgusu(User $user): Builder
    {
        $haricTutulanlar = $this->haricTutulanKullaniciIdleri($user);

        $sorgu = User::query()
            ->whereIn('hesap_tipi', ['user', 'ai'])
            ->where('hesap_durumu', 'aktif')
            ->where('is_admin', false)
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
$gecilen = EslesmeGecilenKullanici::query()
            ->where('gecen_user_id', $user->id)
            ->pluck('gecilen_user_id');

        $eslesilen = Eslesme::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('eslesen_user_id', $user->id);
            })
            ->where('durum', 'aktif')
            ->get(['user_id', 'eslesen_user_id'])
            ->flatMap(function (Eslesme $eslesme) use ($user) {
                return collect([$eslesme->user_id, $eslesme->eslesen_user_id])
                    ->reject(fn($id) => (int) $id === (int) $user->id);
            });

        return $engellenen
            ->merge($gecilen)
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

    private function gunlukHaklariYenile(User $user): User
    {
        $bugun = now()->startOfDay();
        $sonYenileme = $user->son_hak_yenileme_tarihi?->copy()?->startOfDay();

        if ($sonYenileme && $sonYenileme->equalTo($bugun)) {
            return $user;
        }

        $hakLimiti = max(0, (int) $this->ayarServisi->al('gunluk_ucretsiz_hak', 3));

        $user->forceFill([
            'gunluk_ucretsiz_hak' => $hakLimiti,
            'son_hak_yenileme_tarihi' => now(),
        ])->save();

        return $user->fresh();
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
