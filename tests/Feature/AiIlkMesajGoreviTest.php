<?php

use App\Jobs\EslesmeSonrasiAiIlkMesajGorevi;
use App\Models\AiAyar;
use App\Models\Eslesme;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\MesajServisi;
use App\Services\YapayZeka\AiServisi;
use App\Services\YapayZeka\AiMesajZamanlamaServisi;

it('creates an opening ai message after a match', function () {
    $kullanici = User::factory()->create();
    $aiUser = User::factory()->aiKullanici()->create();

    AiAyar::create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
        'ilk_mesaj_atar_mi' => true,
        'hafiza_aktif_mi' => true,
        'kisilik_tipi' => 'samimi',
        'konusma_tonu' => 'dogal',
        'konusma_stili' => 'kisa',
        'mesaj_uzunlugu_min' => 10,
        'mesaj_uzunlugu_max' => 120,
        'minimum_cevap_suresi_saniye' => 0,
        'maksimum_cevap_suresi_saniye' => 0,
        'rastgele_gecikme_dakika' => 0,
    ]);

    $eslesme = Eslesme::create([
        'user_id' => $kullanici->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'baslatan_user_id' => $kullanici->id,
    ]);

    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
        'toplam_mesaj_sayisi' => 0,
    ]);

    $fakeAiServisi = new class extends AiServisi {
        public function __construct() {}

        public function datingIlkMesajUret(
            Sohbet $sohbet,
            User $aiUser,
            ?callable $parcaCallback = null
        ): array {
            return [
                'cevap' => 'Selam, eslesmemize sevindim.',
                'model' => 'gemini-2.5-flash',
                'giris_token' => 0,
                'cikis_token' => 0,
            ];
        }
    };

    (new EslesmeSonrasiAiIlkMesajGorevi($sohbet, $aiUser))
        ->handle(
            $fakeAiServisi,
            app(MesajServisi::class),
            app(AiMesajZamanlamaServisi::class),
        );

    $this->assertDatabaseHas('mesajlar', [
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $aiUser->id,
        'mesaj_metni' => 'Selam, eslesmemize sevindim.',
        'ai_tarafindan_uretildi_mi' => true,
    ]);

    $this->assertDatabaseHas('sohbetler', [
        'id' => $sohbet->id,
        'toplam_mesaj_sayisi' => 1,
    ]);
});
