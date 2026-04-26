<?php

namespace App\Services;

use App\Events\EslesmeOlustu;
use App\Jobs\EslesmeSonrasiAiIlkMesajGorevi;
use App\Jobs\ProcessAiTurnJob;
use App\Models\Engelleme;
use App\Models\Eslesme;
use App\Models\EslesmeGecilenKullanici;
use App\Models\Sohbet;
use App\Models\User;
use App\Notifications\YeniEslesme;
use App\Services\YapayZeka\AiKullaniciHazirlamaServisi;
use App\Services\Users\UserOnlineStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;

class EslesmeServisi
{
    public function __construct(
        private ?AiKullaniciHazirlamaServisi $aiKullaniciHazirlamaServisi = null,
        private ?PuanServisi $puanServisi = null,
        private ?AyarServisi $ayarServisi = null,
        private ?UserOnlineStatusService $userOnlineStatusService = null,
    ) {
        $this->aiKullaniciHazirlamaServisi ??= app(AiKullaniciHazirlamaServisi::class);
        $this->puanServisi ??= app(PuanServisi::class);
        $this->ayarServisi ??= app(AyarServisi::class);
        $this->userOnlineStatusService ??= app(UserOnlineStatusService::class);
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

                    if ($aiUser) {
                        $this->dispatchIlkAiMesaj($sohbet, $aiUser);
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
        $this->senkronizeAiAdayDurumlari($user);
        $adaySorgusu = $this->adaySorgusu($user);

        return [
            'mevcut_puan' => (int) $user->mevcut_puan,
            'gunluk_ucretsiz_hak' => (int) $user->gunluk_ucretsiz_hak,
            'eslesme_baslatma_maliyeti' => $this->eslesmeBaslatmaMaliyeti($user),
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
        $maliyet = $this->eslesmeBaslatmaMaliyeti($user);
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
                'EÅŸleÅŸme baÅŸlatma',
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
     * Rastgele eÅŸleÅŸme (queue job'i tarafÄ±ndan tetiklenir).
     */
    public function rastgeleEslestir(User $user): ?Eslesme
    {
        $this->senkronizeAiAdayDurumlari($user);

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

                if ($aiUser) {
                    $this->dispatchIlkAiMesaj($sohbet, $aiUser);
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
        $this->senkronizeAiAdayDurumlari($user);

        foreach ($this->adayHavuzuSirasi($user) as [$cinsiyet, $sadeceCevrimici]) {
            $aday = $this->adaySec(
                $this->adaySorgusu($user, $cinsiyet, $sadeceCevrimici)->with('fotograflar'),
                $user,
            );

            if ($aday) {
                return $aday;
            }
        }

        return null;
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

    private function adaySorgusu(User $user, ?string $zorunluCinsiyet = null, bool $sadeceCevrimici = false): Builder
    {
        $haricTutulanlar = $this->haricTutulanKullaniciIdleri($user);

        $sorgu = User::query()
            ->whereIn('hesap_tipi', ['user', 'ai'])
            ->where('hesap_durumu', 'aktif')
            ->where('is_admin', false)
            ->whereNotIn('id', $haricTutulanlar);

        if ($sadeceCevrimici) {
            $sorgu->where('cevrim_ici_mi', true);
        }

        $this->cinsiyetFiltresiUygula($sorgu, $user, $zorunluCinsiyet);
        $this->yasFiltresiUygula($sorgu, $user);

        return $sorgu->orderByRaw("CASE WHEN hesap_tipi = 'user' THEN 0 ELSE 1 END");
    }

    private function adaySec(Builder $sorgu, User $user): ?User
    {
        if ($user->super_eslesme_aktif_mi) {
            return $sorgu->inRandomOrder()
                ->limit(40)
                ->get()
                ->sortByDesc(fn(User $aday) => $this->uyumlulukPuani($user, $aday))
                ->first();
        }

        return $sorgu->inRandomOrder()->first();
    }

    private function adayHavuzuSirasi(User $user): array
    {
        $filtre = $user->eslesme_cinsiyet_filtresi;

        if (in_array($filtre, ['kadin', 'erkek'], true)) {
            return [
                [$filtre, true],
                [$filtre, false],
            ];
        }

        $oncelikliCinsiyet = $this->oranliCinsiyetSec($user);
        $yedekCinsiyet = $oncelikliCinsiyet === 'kadin' ? 'erkek' : 'kadin';

        return [
            [$oncelikliCinsiyet, true],
            [$yedekCinsiyet, true],
            [$oncelikliCinsiyet, false],
            [$yedekCinsiyet, false],
            [null, false],
        ];
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

    private function senkronizeAiAdayDurumlari(User $user): void
    {
        $adaylar = User::query()
            ->where('hesap_tipi', 'ai')
            ->where('hesap_durumu', 'aktif')
            ->where('is_admin', false)
            ->whereNotIn('id', $this->haricTutulanKullaniciIdleri($user))
            ->with([
                'aiAyar:id,user_id,aktif_mi,saat_dilimi,uyku_baslangic,uyku_bitis,hafta_sonu_uyku_baslangic,hafta_sonu_uyku_bitis',
                'availabilitySchedules:id,user_id,recurrence_type,specific_date,day_of_week,starts_at,ends_at,status',
            ])
            ->get(['id', 'hesap_tipi', 'hesap_durumu', 'cevrim_ici_mi', 'son_gorulme_tarihi']);

        $this->userOnlineStatusService->syncCollection($adaylar);
    }

    private function cinsiyetFiltresiUygula(Builder $sorgu, User $user, ?string $zorunluCinsiyet = null): void
    {
        $cinsiyet = in_array($user->eslesme_cinsiyet_filtresi, ['kadin', 'erkek'], true)
            ? $user->eslesme_cinsiyet_filtresi
            : $zorunluCinsiyet;

        match ($cinsiyet) {
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

    private function eslesmeBaslatmaMaliyeti(?User $user = null): int
    {
        $varsayilan = max(1, (int) $this->ayarServisi->al('eslesme_baslatma_maliyeti', 8));

        if ($user === null || !in_array($user->eslesme_cinsiyet_filtresi, ['erkek', 'kadin'], true)) {
            return $varsayilan;
        }

        $tur = $user->super_eslesme_aktif_mi ? 'super' : 'normal';
        $anahtar = "{$tur}_eslesme_{$user->eslesme_cinsiyet_filtresi}_maliyeti";

        return max(1, (int) $this->ayarServisi->al($anahtar, $varsayilan));
    }

    private function oranliCinsiyetSec(User $user): string
    {
        $kadinOrani = $this->cinsiyetCikmaOrani($user, 'kadin');
        $erkekOrani = $this->cinsiyetCikmaOrani($user, 'erkek');
        $toplam = $kadinOrani + $erkekOrani;

        if ($toplam <= 0) {
            return random_int(1, 2) === 1 ? 'kadin' : 'erkek';
        }

        return random_int(1, $toplam) <= $kadinOrani ? 'kadin' : 'erkek';
    }

    private function cinsiyetCikmaOrani(User $user, string $cinsiyet): int
    {
        $tur = $user->super_eslesme_aktif_mi ? 'super' : 'normal';
        $varsayilan = match ([$tur, $cinsiyet]) {
            ['normal', 'kadin'] => 34,
            ['normal', 'erkek'] => 66,
            ['super', 'kadin'] => 51,
            ['super', 'erkek'] => 49,
            default => 50,
        };

        $oran = (int) $this->ayarServisi->al("{$tur}_eslesme_{$cinsiyet}_cikma_orani", $varsayilan);

        return max(0, min(100, $oran));
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

    private function dispatchIlkAiMesaj(Sohbet $sohbet, User $aiUser): void
    {
        if (app()->environment('testing')) {
            if ($this->queueIsFaked()) {
                EslesmeSonrasiAiIlkMesajGorevi::dispatch($sohbet, $aiUser);
            }

            return;
        }

        ProcessAiTurnJob::dispatch(
            'dating',
            'first_message',
            $aiUser->id,
            $sohbet->id,
        );
    }

    private function queueIsFaked(): bool
    {
        return Queue::getFacadeRoot() instanceof QueueFake;
    }
}
