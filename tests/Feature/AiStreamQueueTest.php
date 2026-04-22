<?php

use App\Jobs\YapayZekaCevapGorevi;
use App\Models\AiAyar;
use App\Models\Eslesme;
use App\Models\Sohbet;
use App\Models\User;
use App\Models\YapayZekaGorevi;
use App\Services\MesajServisi;
use Illuminate\Support\Facades\Queue;

it('dispatches dating ai replies to the default queue and creates a pending task', function () {
    Queue::fake();

    $gonderen = User::factory()->create();
    $aiUser = User::factory()->aiKullanici()->create();

    AiAyar::create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
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
        'user_id' => $gonderen->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'tekrar_eslesebilir_mi' => false,
        'baslatan_user_id' => $gonderen->id,
    ]);

    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
        'toplam_mesaj_sayisi' => 0,
    ]);

    $mesaj = (new MesajServisi())->gonder($sohbet, $gonderen, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Selam',
    ]);

    $gorev = YapayZekaGorevi::query()
        ->where('gelen_mesaj_id', $mesaj->id)
        ->where('ai_user_id', $aiUser->id)
        ->first();

    expect($gorev)->not->toBeNull();
    expect($gorev->durum)->toBe('bekliyor');

    Queue::assertPushed(YapayZekaCevapGorevi::class, function (YapayZekaCevapGorevi $job) use ($sohbet, $mesaj, $aiUser) {
        return $job->sohbet->is($sohbet)
            && $job->gelenMesaj->is($mesaj)
            && $job->aiUser->is($aiUser);
    });
});

it('provisions gemini settings for ai users before queueing a dating reply', function () {
    Queue::fake();

    $gonderen = User::factory()->create();
    $aiUser = User::factory()->aiKullanici()->create();

    $eslesme = Eslesme::create([
        'user_id' => $gonderen->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'tekrar_eslesebilir_mi' => false,
        'baslatan_user_id' => $gonderen->id,
    ]);

    $sohbet = Sohbet::create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
        'toplam_mesaj_sayisi' => 0,
    ]);

    $mesaj = app(MesajServisi::class)->gonder($sohbet, $gonderen, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Burada misin?',
    ]);

    $aiUser->refresh();

    expect($aiUser->aiAyar)->not->toBeNull();
    expect($aiUser->aiAyar->aktif_mi)->toBeTruthy();
    expect($aiUser->aiAyar->saglayici_tipi)->toBe('gemini');
    expect($aiUser->aiAyar->model_adi)->toBe('gemini-2.5-flash');
    expect($aiUser->aiAyar->minimum_cevap_suresi_saniye)->toBe(5);

    Queue::assertPushed(YapayZekaCevapGorevi::class, function (YapayZekaCevapGorevi $job) use ($sohbet, $mesaj, $aiUser) {
        return $job->sohbet->is($sohbet)
            && $job->gelenMesaj->is($mesaj)
            && $job->aiUser->id === $aiUser->id;
    });

    $this->assertDatabaseHas('yapay_zeka_gorevleri', [
        'gelen_mesaj_id' => $mesaj->id,
        'ai_user_id' => $aiUser->id,
        'durum' => 'cevap_akisi_bekleniyor',
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
    ]);
});
