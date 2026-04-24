<?php

namespace App\Jobs;

use App\Events\InstagramAiCevapHazir;
use App\Exceptions\AiSaglayiciHatasi;
use App\Models\InstagramAiGorevi;
use App\Models\InstagramHesap;
use App\Models\InstagramMesaj;
use App\Services\YapayZeka\AiServisi;
use App\Services\YapayZeka\GeminiSaglayici;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstagramAiCevapGorevi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120];

    public function __construct(
        public InstagramMesaj $gelenMesaj,
        public InstagramHesap $hesap,
    ) {}


    public function handle(AiServisi $aiServisi): void
    {
        $kisi = $this->gelenMesaj->kisi;
        Log::channel('ai')->debug('InstagramAiCevapGorevi handle başladı', [
            'gelen_mesaj_id' => $this->gelenMesaj->id,
            'hesap_id' => $this->hesap->id,
            'kisi_id' => $kisi?->id,
            'gelen_mesaj' => $this->gelenMesaj->toArray(),
        ]);

        if (!$kisi) {
            Log::channel('ai')->warning('InstagramAiCevapGorevi kisi bulunamadigi icin atlandi.', [
                'instagram_mesaj_id' => $this->gelenMesaj->id,
                'instagram_hesap_id' => $this->hesap->id,
            ]);

            return;
        }

        $denemeSayisi = $this->attempts();
        $istekBaslangici = now();
        $sonParcaAt = null;

        $gorev = InstagramAiGorevi::updateOrCreate(
            ['instagram_mesaj_id' => $this->gelenMesaj->id],
            [
                'instagram_hesap_id' => $this->hesap->id,
                'instagram_kisi_id' => $kisi->id,
                'durum' => 'istek_gonderildi',
                'deneme_sayisi' => $denemeSayisi,
                'hata_mesaji' => null,
                'cevap_metni' => null,
                'saglayici_tipi' => $this->hesap->user->aiAyar?->saglayici_tipi ?? 'gemini',
                'model_adi' => $this->hesap->user->aiAyar?->model_adi ?? GeminiSaglayici::MODEL_ADI,
                'istek_baslatildi_at' => $istekBaslangici,
                'son_parca_at' => null,
                'tamamlandi_at' => null,
                'yanit_suresi_ms' => null,
            ]
        );

        if (!$this->gelenMesajHalaEnSonKarsiTarafMesajiMi()) {
            $this->goreviGecersizlestir($gorev, 'Daha yeni bir mesaj alindigi icin gorev gecersiz kaldi.');

            return;
        }

        try {
            $sonuc = $aiServisi->instagramCevapUret(
                $this->hesap,
                $kisi,
                $this->gelenMesaj,
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

            if (!$this->gelenMesajHalaEnSonKarsiTarafMesajiMi()) {
                $this->goreviGecersizlestir(
                    $gorev,
                    'AI yaniti donene kadar daha yeni bir mesaj alindigi icin gorev gecersiz kaldi.',
                    $sonParcaAt
                );

                return;
            }

            $tamamlandiAt = now();
            $yanitSuresiMs = $istekBaslangici->diffInMilliseconds($tamamlandiAt);


            if (empty($sonuc['cevap'])) {
                Log::error('AI cevabı boş geldiği için mesaj kaydedilmedi', [
                    'gelen_mesaj_id' => $this->gelenMesaj->id,
                    'hesap_id' => $this->hesap->id,
                    'kisi_id' => $kisi->id,
                    'ham_cevap' => $sonuc['ham_cevap'] ?? null,
                ]);
                return;
            }

            $cevapMesaj = DB::transaction(function () use (
                $denemeSayisi,
                $gorev,
                $sonuc,
                $kisi,
                $tamamlandiAt,
                $yanitSuresiMs,
                $sonParcaAt
            ) {
                Log::channel('ai')->debug('InstagramAiCevapGorevi AI cevabı DB kaydı öncesi', [
                    'instagram_hesap_id' => $this->hesap->id,
                    'instagram_kisi_id' => $kisi->id,
                    'gonderen_tipi' => 'ai',
                    'mesaj_metni' => $sonuc['cevap'],
                ]);
                $cevapMesaj = InstagramMesaj::create([
                    'instagram_hesap_id' => $this->hesap->id,
                    'instagram_kisi_id' => $kisi->id,
                    'gonderen_tipi' => 'ai',
                    'mesaj_metni' => $sonuc['cevap'],
                    'mesaj_tipi' => 'metin',
                    'gonderildi_mi' => false,
                ]);
                Log::channel('ai')->debug('InstagramAiCevapGorevi AI cevabı DB kaydı sonrası', [
                    'cevap_mesaj_id' => $cevapMesaj->id,
                    'instagram_hesap_id' => $this->hesap->id,
                    'instagram_kisi_id' => $kisi->id,
                    'gonderen_tipi' => 'ai',
                    'mesaj_metni' => $sonuc['cevap'],
                ]);

                $this->gelenMesaj->update([
                    'ai_cevapladi_mi' => true,
                ]);

                $gorev->update([
                    'durum' => 'tamamlandi',
                    'deneme_sayisi' => $denemeSayisi,
                    'hata_mesaji' => null,
                    'cevap_metni' => $sonuc['cevap'],
                    'model_adi' => $sonuc['model'],
                    'tamamlandi_at' => $tamamlandiAt,
                    'son_parca_at' => $sonParcaAt ?? $tamamlandiAt,
                    'yanit_suresi_ms' => $yanitSuresiMs,
                ]);

                return $cevapMesaj;
            });

            try {
                $aiServisi->instagramHafizaKaydet(
                    $this->hesap,
                    $kisi,
                    $this->gelenMesaj,
                    $sonuc['hafiza_kayitlari'] ?? []
                );
            } catch (\Throwable $hafizaHatasi) {
                Log::channel('ai')->warning('InstagramAiCevapGorevi hafiza yazimi atlandi.', [
                    'instagram_mesaj_id' => $this->gelenMesaj->id,
                    'instagram_hesap_id' => $this->hesap->id,
                    'instagram_kisi_id' => $kisi->id,
                    'hata' => $hafizaHatasi->getMessage(),
                ]);
            }

            try {
                InstagramAiCevapHazir::dispatch($cevapMesaj);
            } catch (\Throwable $yayinHatasi) {
                Log::channel('ai')->warning('Instagram AI cevabi yayinlanamadi.', [
                    'instagram_mesaj_id' => $cevapMesaj->id,
                    'instagram_hesap_id' => $this->hesap->id,
                    'instagram_kisi_id' => $kisi->id,
                    'hata' => $yayinHatasi->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            $yenidenDene = $e instanceof AiSaglayiciHatasi
                ? $e->yenidenDenenebilir && $denemeSayisi < $this->tries
                : $denemeSayisi < $this->tries;
            $gecikme = $this->sonrakiBeklemeSuresi($denemeSayisi);
            $tamamlandiAt = now();

            $gorev->update([
                'durum' => $yenidenDene ? 'yeniden_denecek' : 'basarisiz',
                'deneme_sayisi' => $denemeSayisi,
                'hata_mesaji' => mb_substr($e->getMessage(), 0, 1000),
                'son_parca_at' => $sonParcaAt,
                'tamamlandi_at' => $yenidenDene ? null : $tamamlandiAt,
                'yanit_suresi_ms' => $istekBaslangici->diffInMilliseconds($tamamlandiAt),
            ]);

            if ($yenidenDene) {
                Log::channel('ai')->warning('InstagramAiCevapGorevi gecici hata nedeniyle yeniden denenecek.', [
                    'gorev_id' => $gorev->id,
                    'deneme' => $denemeSayisi,
                    'gecikme_saniye' => $gecikme,
                    'hata' => $e->getMessage(),
                ]);

                $this->release($gecikme);

                return;
            }

            Log::channel('ai')->error('InstagramAiCevapGorevi basarisiz.', [
                'gorev_id' => $gorev->id,
                'deneme' => $denemeSayisi,
                'hata' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    private function gelenMesajHalaEnSonKarsiTarafMesajiMi(): bool
    {
        $sonMesajId = InstagramMesaj::query()
            ->where('instagram_hesap_id', $this->hesap->id)
            ->where('instagram_kisi_id', $this->gelenMesaj->instagram_kisi_id)
            ->where('gonderen_tipi', 'karsi_taraf')
            ->max('id');

        return (int) $sonMesajId === (int) $this->gelenMesaj->id;
    }

    private function goreviGecersizlestir(
        InstagramAiGorevi $gorev,
        string $mesaj,
        $sonParcaAt = null
    ): void {
        $gorev->update([
            'durum' => 'gecersiz',
            'hata_mesaji' => $mesaj,
            'son_parca_at' => $sonParcaAt,
            'tamamlandi_at' => now(),
        ]);

        $this->gelenMesaj->update([
            'ai_cevapladi_mi' => true,
        ]);

        Log::channel('ai')->info('InstagramAiCevapGorevi gecersizlestirildi.', [
            'gorev_id' => $gorev->id,
            'instagram_mesaj_id' => $this->gelenMesaj->id,
            'hata' => $mesaj,
        ]);
    }

    private function sonrakiBeklemeSuresi(int $denemeSayisi): int
    {
        if ($denemeSayisi <= 0) {
            return $this->backoff[0] ?? 10;
        }

        return $this->backoff[$denemeSayisi - 1] ?? end($this->backoff) ?: 120;
    }
}
