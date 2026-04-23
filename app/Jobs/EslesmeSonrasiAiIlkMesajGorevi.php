<?php

namespace App\Jobs;

use App\Models\Sohbet;
use App\Models\User;
use App\Services\MesajServisi;
use App\Services\YapayZeka\AiServisi;
use App\Services\YapayZeka\AiMesajZamanlamaServisi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EslesmeSonrasiAiIlkMesajGorevi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public Sohbet $sohbet,
        public User $aiUser,
    ) {}

    public function handle(
        AiServisi $aiServisi,
        MesajServisi $mesajServisi,
        AiMesajZamanlamaServisi $aiMesajZamanlamaServisi,
    ): void
    {
        $sohbet = $this->sohbet->fresh(['eslesme', 'mesajlar']);
        $aiUser = $this->aiUser->fresh(['aiAyar']);

        if (!$sohbet || !$aiUser || $aiUser->hesap_tipi !== 'ai') {
            return;
        }

        if ($sohbet->mesajlar()->exists()) {
            return;
        }

        if (!$aiUser->aiAyar?->aktif_mi || !$aiUser->aiAyar?->ilk_mesaj_atar_mi) {
            return;
        }

        $zamanlama = $aiMesajZamanlamaServisi->ilkMesajDurumu($sohbet, $aiUser);
        if (!$zamanlama['hemen_calistir']) {
            if ($zamanlama['sonraki_kontrol_at']) {
                self::dispatch($sohbet, $aiUser)
                    ->delay($zamanlama['sonraki_kontrol_at']);
            }

            return;
        }

        $sonuc = $aiServisi->datingIlkMesajUret($sohbet, $aiUser);
        $ilkMesaj = trim((string) ($sonuc['cevap'] ?? ''));

        if ($ilkMesaj === '') {
            Log::channel('ai')->warning('EslesmeSonrasiAiIlkMesajGorevi bos mesaj uretti.', [
                'sohbet_id' => $sohbet->id,
                'ai_user_id' => $aiUser->id,
            ]);

            return;
        }

        Log::stack(['single', 'ai'])->info('EslesmeSonrasiAiIlkMesajGorevi AI ilk mesaji uretildi.', [
            'sohbet_id' => $sohbet->id,
            'ai_user_id' => $aiUser->id,
            'cevap_metni' => $ilkMesaj,
            'ham_cevap' => $sonuc['ham_cevap'] ?? null,
            'model' => $sonuc['model'] ?? null,
            'giris_token' => $sonuc['giris_token'] ?? null,
            'cikis_token' => $sonuc['cikis_token'] ?? null,
        ]);

        $mesajServisi->gonderAiMesaji($sohbet, $aiUser, [
            'mesaj_tipi' => 'metin',
            'mesaj_metni' => $ilkMesaj,
        ]);
    }
}
