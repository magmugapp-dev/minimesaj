<?php

namespace App\Jobs;

use App\Exceptions\AiSaglayiciHatasi;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Models\YapayZekaGorevi;
use App\Services\MesajServisi;
use App\Services\YapayZeka\AiServisi;
use App\Services\YapayZeka\AiMesajZamanlamaServisi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class YapayZekaCevapGorevi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public Sohbet $sohbet,
        public Mesaj $gelenMesaj,
        public User $aiUser,
    ) {}

    public function handle(
        AiServisi $aiServisi,
        MesajServisi $mesajServisi,
        AiMesajZamanlamaServisi $aiMesajZamanlamaServisi,
    ): void
    {
        $this->sohbet = $this->sohbet->fresh('eslesme') ?? $this->sohbet;
        $this->gelenMesaj = $this->gelenMesaj->fresh() ?? $this->gelenMesaj;
        $this->aiUser = $this->aiUser->fresh('aiAyar') ?? $this->aiUser;

        $denemeSayisi = $this->attempts();
        $istekBaslangici = now();
        $sonParcaAt = null;

        $gorev = YapayZekaGorevi::updateOrCreate(
            [
                'gelen_mesaj_id' => $this->gelenMesaj->id,
                'ai_user_id' => $this->aiUser->id,
            ],
            [
                'sohbet_id' => $this->sohbet->id,
                'durum' => 'istek_gonderildi',
                'deneme_sayisi' => $denemeSayisi,
                'hata_mesaji' => null,
                'cevap_metni' => null,
                'saglayici_tipi' => $this->aiUser->aiAyar?->saglayici_tipi ?? 'gemini',
                'model_adi' => $this->aiUser->aiAyar?->model_adi ?? 'gemini-2.5-flash',
                'giris_token_sayisi' => null,
                'cikis_token_sayisi' => null,
                'istek_baslatildi_at' => $istekBaslangici,
                'son_parca_at' => null,
                'tamamlandi_at' => null,
                'yanit_suresi_ms' => null,
            ]
        );

        $zamanlama = $aiMesajZamanlamaServisi->sohbetCevabiDurumu(
            $this->gelenMesaj,
            $this->aiUser,
            $istekBaslangici,
        );

        if (!$zamanlama['hemen_calistir']) {
            $gorev->update([
                'durum' => $this->bekleyenDurumEtiketi($zamanlama['bekleme_nedeni']),
                'deneme_sayisi' => $denemeSayisi,
                'istek_baslatildi_at' => null,
                'son_parca_at' => null,
                'tamamlandi_at' => null,
            ]);

            if ($zamanlama['sonraki_kontrol_at']) {
                self::dispatch($this->sohbet, $this->gelenMesaj, $this->aiUser)
                    ->delay($zamanlama['sonraki_kontrol_at']);
            }

            return;
        }

        if ($this->dahaYeniKullaniciMesajiVar()) {
            $gorev->update([
                'durum' => 'atlandi',
                'deneme_sayisi' => $denemeSayisi,
                'hata_mesaji' => 'Daha yeni bir kullanici mesaji bulundugu icin bu cevap atlandi.',
                'tamamlandi_at' => now(),
            ]);

            return;
        }

        try {
            $sonuc = $aiServisi->datingCevapUret(
                $this->sohbet,
                $this->gelenMesaj,
                $this->aiUser,
                function (string $parca, array $veri) use (&$sonParcaAt, $gorev): void {
                    if (trim($parca) === '') {
                        return;
                    }

                    $sonParcaAt = now();

                    if ($gorev->durum !== 'yanit_akiyor') {
                        $gorev->forceFill([
                            'durum' => 'yanit_akiyor',
                            'son_parca_at' => $sonParcaAt,
                        ])->save();
                    }
                }
            );

            $tamamlandiAt = now();
            $yanitSuresiMs = $istekBaslangici->diffInMilliseconds($tamamlandiAt);

            Log::stack(['single', 'ai'])->info('YapayZekaCevapGorevi AI cevabi uretildi.', [
                'sohbet_id' => $this->sohbet->id,
                'gelen_mesaj_id' => $this->gelenMesaj->id,
                'ai_user_id' => $this->aiUser->id,
                'cevap_metni' => $sonuc['cevap'] ?? null,
                'ham_cevap' => $sonuc['ham_cevap'] ?? null,
                'model' => $sonuc['model'] ?? null,
                'giris_token' => $sonuc['giris_token'] ?? null,
                'cikis_token' => $sonuc['cikis_token'] ?? null,
                'yanit_suresi_ms' => $yanitSuresiMs,
            ]);

            $aiMesaji = $mesajServisi->gonderAiMesaji($this->sohbet, $this->aiUser, [
                'mesaj_tipi' => 'metin',
                'mesaj_metni' => $sonuc['cevap'],
            ]);

            if ($this->gecikmeUygulanmaliMi($sonuc['gecikme'] ?? false, $sonuc['cevap'] ?? '')) {
                $aiMesajZamanlamaServisi->sessizlikPenceresiBaslat($this->sohbet, $this->aiUser, $aiMesaji);
            } else {
                $aiMesajZamanlamaServisi->sessizlikPenceresiniTemizle($this->sohbet);
            }

            DB::transaction(function () use (
                $sonuc,
                $gorev,
                $denemeSayisi,
                $tamamlandiAt,
                $yanitSuresiMs,
                $sonParcaAt
            ) {
                $gorev->update([
                    'durum' => 'tamamlandi',
                    'deneme_sayisi' => $denemeSayisi,
                    'cevap_metni' => $sonuc['cevap'],
                    'hata_mesaji' => null,
                    'model_adi' => $sonuc['model'],
                    'giris_token_sayisi' => $sonuc['giris_token'],
                    'cikis_token_sayisi' => $sonuc['cikis_token'],
                    'tamamlandi_at' => $tamamlandiAt,
                    'son_parca_at' => $sonParcaAt ?? $tamamlandiAt,
                    'yanit_suresi_ms' => $yanitSuresiMs,
                ]);
            });

            try {
                $aiServisi->datingHafizaKaydet(
                    $this->sohbet,
                    $this->gelenMesaj,
                    $this->aiUser,
                    $sonuc['hafiza_kayitlari'] ?? []
                );
            } catch (\Throwable $hafizaHatasi) {
                Log::channel('ai')->warning('YapayZekaCevapGorevi hafiza yazimi atlandi.', [
                    'sohbet_id' => $this->sohbet->id,
                    'gelen_mesaj_id' => $this->gelenMesaj->id,
                    'ai_user_id' => $this->aiUser->id,
                    'hata' => $hafizaHatasi->getMessage(),
                ]);
            }

        } catch (\Throwable $e) {
            $yenidenDene = $e instanceof AiSaglayiciHatasi
                ? $e->yenidenDenenebilir && $denemeSayisi < $this->tries
                : $denemeSayisi < $this->tries;
            $tamamlandiAt = now();

            $gorev->update([
                'durum' => $yenidenDene ? 'yeniden_denecek' : 'basarisiz',
                'deneme_sayisi' => $denemeSayisi,
                'hata_mesaji' => mb_substr($e->getMessage(), 0, 500),
                'son_parca_at' => $sonParcaAt,
                'tamamlandi_at' => $yenidenDene ? null : $tamamlandiAt,
                'yanit_suresi_ms' => $istekBaslangici->diffInMilliseconds($tamamlandiAt),
            ]);

            if ($yenidenDene) {
                Log::channel('ai')->warning('YapayZekaCevapGorevi gecici hata nedeniyle yeniden denenecek.', [
                    'gorev_id' => $gorev->id,
                    'deneme' => $denemeSayisi,
                    'gecikme_saniye' => $this->backoff,
                    'hata' => $e->getMessage(),
                ]);

                $this->release($this->backoff);

                return;
            }

            Log::channel('ai')->error('YapayZekaCevapGorevi basarisiz.', [
                'gorev_id' => $gorev->id,
                'hata' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    private function bekleyenDurumEtiketi(?string $beklemeNedeni): string
    {
        return match ($beklemeNedeni) {
            'aktif_saat_disinda' => 'aktif_saat_bekleniyor',
            'cevrim_disi' => 'cevrim_disi_bekleniyor',
            'sohbet_sessizde' => 'sessiz_mod_bekleniyor',
            'cevap_akisi' => 'cevap_akisi_bekleniyor',
            'ai_pasif' => 'ai_pasif',
            default => 'bekliyor',
        };
    }

    private function dahaYeniKullaniciMesajiVar(): bool
    {
        return Mesaj::query()
            ->where('sohbet_id', $this->sohbet->id)
            ->where('gonderen_user_id', '!=', $this->aiUser->id)
            ->where('id', '>', $this->gelenMesaj->id)
            ->exists();
    }

    private function gecikmeUygulanmaliMi(bool $bayrak, string $cevap): bool
    {
        if ($bayrak) {
            return true;
        }

        return $this->kapanisTonluMesajMi($this->gelenMesaj->mesaj_metni ?? '')
            || $this->kapanisTonluMesajMi($cevap);
    }

    private function kapanisTonluMesajMi(string $metin): bool
    {
        $normalize = Str::of($metin)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();

        $ifadeler = [
            'gorusuruz',
            'iyi geceler',
            'tatli ruyalar',
            'yarin konusuruz',
            'yarin yazarim',
            'simdi cikmam lazim',
            'simdi kacmam lazim',
            'sonra yazarim',
            'kendine iyi bak',
            'uyuyayim',
        ];

        foreach ($ifadeler as $ifade) {
            if (str_contains($normalize, $ifade)) {
                return true;
            }
        }

        return false;
    }
}
