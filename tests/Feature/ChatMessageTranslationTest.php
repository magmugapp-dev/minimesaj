<?php

use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\YapayZeka\GeminiSaglayici;
use Laravel\Sanctum\Sanctum;

class FakeTranslationGeminiProvider extends GeminiSaglayici
{
    public int $calls = 0;

    public function tamamlaStream(
        array $mesajlar,
        array $parametreler = [],
        ?callable $parcaCallback = null
    ): array {
        $this->calls++;

        return [
            'cevap' => json_encode(['reply' => 'Merhaba, nasılsın?', 'memory' => []], JSON_UNESCAPED_UNICODE),
            'giris_token' => 0,
            'cikis_token' => 0,
            'model' => 'fake-gemini',
        ];
    }
}

it('translates only incoming text messages and caches the result', function () {
    $viewer = User::factory()->create(['dil' => 'tr', 'hesap_durumu' => 'aktif']);
    $peer = User::factory()->create(['dil' => 'en', 'hesap_durumu' => 'aktif']);

    $match = Eslesme::query()->create([
        'user_id' => $viewer->id,
        'eslesen_user_id' => $peer->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
        'baslatan_user_id' => $viewer->id,
    ]);
    $conversation = Sohbet::query()->create([
        'eslesme_id' => $match->id,
        'durum' => 'aktif',
    ]);
    $incoming = Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $peer->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Hello, how are you?',
        'dil_kodu' => 'en',
        'dil_adi' => 'English',
    ]);

    $fake = new FakeTranslationGeminiProvider();
    app()->instance(GeminiSaglayici::class, $fake);
    Sanctum::actingAs($viewer);

    $this->postJson("/api/dating/sohbetler/{$conversation->id}/mesajlar/{$incoming->id}/ceviri")
        ->assertOk()
        ->assertJsonPath('ceviri.metin', 'Merhaba, nasılsın?')
        ->assertJsonPath('ceviri.hedef_dil_kodu', 'tr');

    $this->postJson("/api/dating/sohbetler/{$conversation->id}/mesajlar/{$incoming->id}/ceviri")
        ->assertOk()
        ->assertJsonPath('ceviri.metin', 'Merhaba, nasılsın?');

    expect($fake->calls)->toBe(1);

    $this->getJson("/api/dating/sohbetler/{$conversation->id}/mesajlar")
        ->assertOk()
        ->assertJsonPath('data.0.ceviri', null);
});

it('does not translate outgoing messages', function () {
    $viewer = User::factory()->create(['dil' => 'tr', 'hesap_durumu' => 'aktif']);
    $peer = User::factory()->create(['hesap_durumu' => 'aktif']);

    $match = Eslesme::query()->create([
        'user_id' => $viewer->id,
        'eslesen_user_id' => $peer->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
        'baslatan_user_id' => $viewer->id,
    ]);
    $conversation = Sohbet::query()->create([
        'eslesme_id' => $match->id,
        'durum' => 'aktif',
    ]);
    $outgoing = Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $viewer->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Selam',
    ]);

    Sanctum::actingAs($viewer);

    $this->postJson("/api/dating/sohbetler/{$conversation->id}/mesajlar/{$outgoing->id}/ceviri")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Sadece gelen mesajlar cevrilebilir.');
});
