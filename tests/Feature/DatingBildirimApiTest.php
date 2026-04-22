<?php

use App\Jobs\EslesmeSonrasiAiIlkMesajGorevi;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\PushDeviceToken;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\MesajServisi;
use App\Notifications\Messages\FcmMessage;
use App\Notifications\YeniMesaj;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

it('registers and reassigns a push device token to the latest authenticated user', function () {
    $ilkKullanici = User::factory()->create();
    $ikinciKullanici = User::factory()->create();

    Sanctum::actingAs($ilkKullanici);

    $this->postJson('/api/dating/bildirim-cihazlari', [
        'token' => 'cihaz-token-1',
        'platform' => 'android',
        'cihaz_adi' => 'Pixel',
        'uygulama_versiyonu' => '1.0.0',
        'dil' => 'tr',
        'bildirim_izni' => true,
    ])->assertCreated()
        ->assertJsonPath('cihaz.platform', 'android');

    expect(PushDeviceToken::query()->count())->toBe(1);
    expect(PushDeviceToken::query()->first()?->user_id)->toBe($ilkKullanici->id);

    Sanctum::actingAs($ikinciKullanici);

    $this->postJson('/api/dating/bildirim-cihazlari', [
        'token' => 'cihaz-token-1',
        'platform' => 'ios',
        'cihaz_adi' => 'iPhone',
        'uygulama_versiyonu' => '1.1.0',
        'dil' => 'en',
        'bildirim_izni' => true,
    ])->assertOk()
        ->assertJsonPath('cihaz.platform', 'ios');

    expect(PushDeviceToken::query()->count())->toBe(1);
    expect(PushDeviceToken::query()->first()?->user_id)->toBe($ikinciKullanici->id);
});

it('deletes only the current users push device token', function () {
    $kullanici = User::factory()->create();
    $digerKullanici = User::factory()->create();

    PushDeviceToken::query()->create([
        'user_id' => $kullanici->id,
        'token' => 'silinecek-token',
        'platform' => 'android',
        'bildirim_izni' => true,
    ]);

    PushDeviceToken::query()->create([
        'user_id' => $digerKullanici->id,
        'token' => 'kalacak-token',
        'platform' => 'ios',
        'bildirim_izni' => true,
    ]);

    Sanctum::actingAs($kullanici);

    $this->deleteJson('/api/dating/bildirim-cihazlari', [
        'token' => 'silinecek-token',
    ])->assertOk();

    $this->assertDatabaseMissing('push_device_tokens', [
        'user_id' => $kullanici->id,
        'token' => 'silinecek-token',
    ]);

    $this->assertDatabaseHas('push_device_tokens', [
        'user_id' => $digerKullanici->id,
        'token' => 'kalacak-token',
    ]);
});

it('updates notification settings and reflects them in the api response', function () {
    $kullanici = User::factory()->create([
        'bildirimler_acik_mi' => true,
        'titresim_acik_mi' => true,
    ]);

    Sanctum::actingAs($kullanici);

    $this->patchJson('/api/dating/bildirim-ayarlari', [
        'bildirimler_acik_mi' => false,
        'titresim_acik_mi' => false,
    ])->assertOk()
        ->assertJsonPath('kullanici.bildirimler_acik_mi', false)
        ->assertJsonPath('kullanici.titresim_acik_mi', false);

    expect($kullanici->fresh()->bildirimler_acik_mi)->toBeFalse();
    expect($kullanici->fresh()->titresim_acik_mi)->toBeFalse();
});

it('builds a standardized yeni mesaj notification payload for mobile clients', function () {
    $gonderen = User::factory()->create([
        'ad' => 'Aylin',
    ]);

    $alici = User::factory()->create([
        'bildirimler_acik_mi' => true,
    ]);

    PushDeviceToken::query()->create([
        'user_id' => $alici->id,
        'token' => 'mesaj-tokeni',
        'platform' => 'android',
        'bildirim_izni' => true,
    ]);

    $eslesme = Eslesme::query()->create([
        'user_id' => $gonderen->id,
        'eslesen_user_id' => $alici->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
        'baslatan_user_id' => $gonderen->id,
    ]);

    $sohbet = Sohbet::query()->create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
    ]);

    $mesaj = Mesaj::query()->create([
        'sohbet_id' => $sohbet->id,
        'gonderen_user_id' => $gonderen->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Merhaba, yeni eslestik.',
        'okundu_mu' => false,
    ]);

    $bildirim = new YeniMesaj($mesaj, $gonderen);
    $payload = $bildirim->toArray($alici);
    $fcmMesaji = $bildirim->toFcm($alici);

    expect($payload['tip'])->toBe('yeni_mesaj')
        ->and($payload['rota'])->toBe('chat')
        ->and($payload['rota_parametreleri']['sohbet_id'])->toBe((string) $sohbet->id)
        ->and($payload['okunmamis_sayisi'])->toBe(1)
        ->and($payload['gonderen_adi'])->toBe('Aylin');

    expect($fcmMesaji)->toBeInstanceOf(FcmMessage::class)
        ->and($fcmMesaji->data['tip'])->toBe('yeni_mesaj')
        ->and($fcmMesaji->data['rota'])->toBe('chat');

    expect($alici->routeNotificationForFcm())->toBe(['mesaj-tokeni']);
});

it('returns no fcm tokens when notifications are disabled for the user', function () {
    $kullanici = User::factory()->create([
        'bildirimler_acik_mi' => false,
    ]);

    PushDeviceToken::query()->create([
        'user_id' => $kullanici->id,
        'token' => 'kapali-token',
        'platform' => 'android',
        'bildirim_izni' => true,
    ]);

    expect($kullanici->routeNotificationForFcm())->toBe([]);
});

it('creates the yeni mesaj notification immediately after a message is sent', function () {
    config()->set('broadcasting.default', 'null');

    $gonderen = User::factory()->create();
    $alici = User::factory()->create([
        'bildirimler_acik_mi' => true,
    ]);

    $eslesme = Eslesme::query()->create([
        'user_id' => $gonderen->id,
        'eslesen_user_id' => $alici->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
        'baslatan_user_id' => $gonderen->id,
    ]);

    $sohbet = Sohbet::query()->create([
        'eslesme_id' => $eslesme->id,
        'durum' => 'aktif',
    ]);

    $mesaj = app(MesajServisi::class)->gonder($sohbet, $gonderen, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Selam, burada misin?',
    ]);

    expect($mesaj->exists)->toBeTrue();

    $alici->refresh();

    expect($alici->notifications()->count())->toBe(1)
        ->and($alici->unreadNotifications()->count())->toBe(1)
        ->and($alici->notifications()->first()?->type)->toBe(YeniMesaj::class);
});

it('includes ai users in discovery and auto-matches them when liked', function () {
    $kullanici = User::factory()->create([
        'hesap_tipi' => 'user',
        'hesap_durumu' => 'aktif',
        'cevrim_ici_mi' => true,
    ]);

    $aiKullanici = User::factory()->create([
        'hesap_tipi' => 'ai',
        'hesap_durumu' => 'aktif',
        'cevrim_ici_mi' => true,
    ]);

    Sanctum::actingAs($kullanici);

    $this->getJson('/api/dating/kesfet')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $aiKullanici->id,
            'hesap_tipi' => 'ai',
        ]);

    $this->postJson("/api/dating/begeni/{$aiKullanici->id}")
        ->assertOk()
        ->assertJsonPath('durum', 'eslesme');

    $this->assertDatabaseHas('begeniler', [
        'begenen_user_id' => $aiKullanici->id,
        'begenilen_user_id' => $kullanici->id,
        'eslesmeye_donustu_mu' => true,
    ]);

    $this->assertDatabaseHas('eslesmeler', [
        'user_id' => $kullanici->id,
        'eslesen_user_id' => $aiKullanici->id,
        'durum' => 'aktif',
    ]);

    $this->assertDatabaseHas('ai_ayarlar', [
        'user_id' => $aiKullanici->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
    ]);
});

it('queues an ai first message after an automatic ai match is created', function () {
    Queue::fake();

    $kullanici = User::factory()->create([
        'hesap_tipi' => 'user',
        'hesap_durumu' => 'aktif',
        'cevrim_ici_mi' => true,
    ]);

    $aiKullanici = User::factory()->create([
        'hesap_tipi' => 'ai',
        'hesap_durumu' => 'aktif',
        'cevrim_ici_mi' => true,
    ]);

    Sanctum::actingAs($kullanici);

    $this->postJson("/api/dating/begeni/{$aiKullanici->id}")
        ->assertOk()
        ->assertJsonPath('durum', 'eslesme');

    Queue::assertPushed(EslesmeSonrasiAiIlkMesajGorevi::class, function (EslesmeSonrasiAiIlkMesajGorevi $job) use ($aiKullanici) {
        return $job->aiUser->id === $aiKullanici->id
            && $job->sohbet->exists;
    });
});
