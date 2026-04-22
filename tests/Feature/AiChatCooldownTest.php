<?php

use App\Jobs\YapayZekaCevapGorevi;
use App\Models\AiAyar;
use App\Models\Eslesme;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\MesajServisi;
use App\Services\YapayZeka\AiMesajZamanlamaServisi;
use App\Services\YapayZeka\AiServisi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

it('waits until chat cooldown ends before answering a new dating message', function () {
    Queue::fake();
    Carbon::setTestNow('2026-04-16 14:00:00');

    $gonderen = User::factory()->create();
    $aiUser = User::factory()->aiKullanici()->create([
        'cevrim_ici_mi' => true,
    ]);

    AiAyar::create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
        'minimum_cevap_suresi_saniye' => 5,
        'maksimum_cevap_suresi_saniye' => 40,
        'rastgele_gecikme_dakika' => 15,
        'uyku_baslangic' => '23:00',
        'uyku_bitis' => '07:30',
        'saat_dilimi' => 'Europe/Istanbul',
    ]);

    $eslesme = Eslesme::create([
        'user_id' => $gonderen->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'baslatan_user_id' => $gonderen->id,
    ]);

    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
        'ai_sessiz_mod_bitis_at' => now()->copy()->addMinutes(10),
    ]);

    $mesaj = app(MesajServisi::class)->gonder($sohbet, $gonderen, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Uyumadin mi daha?',
    ]);

    $durum = app(AiMesajZamanlamaServisi::class)->sohbetCevabiDurumu(
        $mesaj,
        $aiUser->fresh('aiAyar'),
        now(),
    );

    expect($durum['bekleme_nedeni'])->toBe('sohbet_sessizde');
    expect($durum['sonraki_kontrol_at']?->equalTo($sohbet->ai_sessiz_mod_bitis_at))->toBeTrue();

    $this->assertDatabaseHas('yapay_zeka_gorevleri', [
        'gelen_mesaj_id' => $mesaj->id,
        'ai_user_id' => $aiUser->id,
        'durum' => 'sessiz_mod_bekleniyor',
    ]);

    Carbon::setTestNow();
});

it('starts cooldown after a closing exchange even when ai flag is false', function () {
    Carbon::setTestNow('2026-04-16 15:00:00');

    $gonderen = User::factory()->create();
    $aiUser = User::factory()->aiKullanici()->create([
        'cevrim_ici_mi' => true,
    ]);

    AiAyar::create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
        'minimum_cevap_suresi_saniye' => 0,
        'maksimum_cevap_suresi_saniye' => 0,
        'rastgele_gecikme_dakika' => 15,
        'uyku_baslangic' => '23:00',
        'uyku_bitis' => '07:30',
        'saat_dilimi' => 'Europe/Istanbul',
    ]);

    $eslesme = Eslesme::create([
        'user_id' => $gonderen->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'baslatan_user_id' => $gonderen->id,
    ]);

    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
    ]);

    $mesaj = app(MesajServisi::class)->gonder($sohbet, $gonderen, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'tamam gorusuruz',
    ]);

    $fakeAiServisi = new class extends AiServisi {
        public function __construct() {}

        public function datingCevapUret(
            Sohbet $sohbet,
            \App\Models\Mesaj $gelenMesaj,
            User $aiUser,
            ?callable $parcaCallback = null
        ): array {
            return [
                'cevap' => 'Tamamdir, sonra konusuruz.',
                'ham_cevap' => '{"reply":"Tamamdir, sonra konusuruz.","memory":[],"gecikme":false}',
                'hafiza_kayitlari' => [],
                'gecikme' => false,
                'model' => 'gemini-2.5-flash',
                'giris_token' => 0,
                'cikis_token' => 0,
            ];
        }
    };

    (new YapayZekaCevapGorevi($sohbet, $mesaj, $aiUser))
        ->handle(
            $fakeAiServisi,
            app(MesajServisi::class),
            app(AiMesajZamanlamaServisi::class),
        );

    $sohbet->refresh();

    expect($sohbet->ai_sessiz_mod_bitis_at)->not->toBeNull();
    expect($sohbet->ai_sessiz_mod_tetikleyen_mesaj_id)->not->toBeNull();

    Carbon::setTestNow();
});
