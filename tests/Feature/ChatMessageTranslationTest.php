<?php

use App\Models\Ayar;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Services\YapayZeka\GeminiModelPolicy;
use App\Services\YapayZeka\GeminiSaglayici;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class FakeTranslationGeminiProvider extends GeminiSaglayici
{
    public int $calls = 0;

    public array $requests = [];

    public function tamamlaStream(
        array $mesajlar,
        array $parametreler = [],
        ?callable $parcaCallback = null
    ): array {
        $this->calls++;
        $this->requests[] = $parametreler;

        return [
            'cevap' => json_encode(['reply' => 'Merhaba, nasilsin?', 'memory' => []], JSON_UNESCAPED_UNICODE),
            'giris_token' => 0,
            'cikis_token' => 0,
            'model' => $parametreler['model_chain'][0] ?? $parametreler['model_adi'] ?? 'fake-gemini',
        ];
    }
}

it('translates only incoming text messages and caches the result', function () {
    $viewer = User::factory()->create(['dil' => 'tr', 'hesap_durumu' => 'aktif']);
    $peer = User::factory()->create(['dil' => 'en', 'hesap_durumu' => 'aktif']);
    $conversation = chatTranslationConversation($viewer, $peer);
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
        ->assertJsonPath('ceviri.metin', 'Merhaba, nasilsin?')
        ->assertJsonPath('ceviri.hedef_dil_kodu', 'tr');

    $this->postJson("/api/dating/sohbetler/{$conversation->id}/mesajlar/{$incoming->id}/ceviri")
        ->assertOk()
        ->assertJsonPath('ceviri.metin', 'Merhaba, nasilsin?');

    expect($fake->calls)->toBe(1)
        ->and($fake->requests)->toHaveCount(1);

    $params = $fake->requests[0];
    expect($params['model_adi'])->toBe(GeminiModelPolicy::TERTIARY_MODEL)
        ->and($params['model_chain'])->toBe([
            GeminiModelPolicy::TERTIARY_MODEL,
            GeminiModelPolicy::SECONDARY_MODEL,
        ])
        ->and(in_array(GeminiModelPolicy::PRIMARY_MODEL, $params['model_chain'], true))->toBeFalse()
        ->and($params['temperature'])->toBe(0.0)
        ->and($params['top_p'])->toBe(0.3)
        ->and($params['max_output_tokens'])->toBe(256)
        ->and($params['stream_timeout_seconds'])->toBe(10)
        ->and($params['connect_timeout_seconds'])->toBe(3);

    $this->getJson("/api/dating/sohbetler/{$conversation->id}/mesajlar")
        ->assertOk()
        ->assertJsonPath('data.0.ceviri', null);
});

it('falls back from lite to flash for translations and never uses pro', function () {
    Ayar::query()->updateOrCreate(
        ['anahtar' => 'gemini_api_key'],
        [
            'deger' => 'test-gemini-key',
            'grup' => 'api',
            'tip' => 'text',
        ]
    );

    $viewer = User::factory()->create(['dil' => 'tr', 'hesap_durumu' => 'aktif']);
    $peer = User::factory()->create(['dil' => 'en', 'hesap_durumu' => 'aktif']);
    $conversation = chatTranslationConversation($viewer, $peer);
    $incoming = Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $peer->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Hello, how are you?',
        'dil_kodu' => 'en',
        'dil_adi' => 'English',
    ]);

    $requestedModels = [];
    Http::fake(function (Request $request) use (&$requestedModels) {
        $model = chatTranslationRequestedModel($request);
        $requestedModels[] = $model;

        if ($model === GeminiModelPolicy::TERTIARY_MODEL) {
            return Http::response(
                chatTranslationGeminiErrorBody(503, 'UNAVAILABLE', 'Lite model is overloaded.'),
                503
            );
        }

        return Http::response(chatTranslationGeminiStreamBody('Merhaba, nasilsin?'), 200, [
            'Content-Type' => 'text/event-stream',
        ]);
    });

    Sanctum::actingAs($viewer);

    $this->postJson("/api/dating/sohbetler/{$conversation->id}/mesajlar/{$incoming->id}/ceviri")
        ->assertOk()
        ->assertJsonPath('ceviri.metin', 'Merhaba, nasilsin?')
        ->assertJsonPath('ceviri.model_adi', GeminiModelPolicy::SECONDARY_MODEL);

    expect($requestedModels)->toBe([
        GeminiModelPolicy::TERTIARY_MODEL,
        GeminiModelPolicy::SECONDARY_MODEL,
    ])->and(in_array(GeminiModelPolicy::PRIMARY_MODEL, $requestedModels, true))->toBeFalse();
});

it('does not translate outgoing messages', function () {
    $viewer = User::factory()->create(['dil' => 'tr', 'hesap_durumu' => 'aktif']);
    $peer = User::factory()->create(['hesap_durumu' => 'aktif']);
    $conversation = chatTranslationConversation($viewer, $peer);
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

function chatTranslationConversation(User $viewer, User $peer): Sohbet
{
    $match = Eslesme::query()->create([
        'user_id' => $viewer->id,
        'eslesen_user_id' => $peer->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
        'baslatan_user_id' => $viewer->id,
    ]);

    return Sohbet::query()->create([
        'eslesme_id' => $match->id,
        'durum' => 'aktif',
    ]);
}

function chatTranslationGeminiStreamBody(string $reply): string
{
    return implode("\n\n", [
        'data: ' . json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode([
                            'reply' => $reply,
                            'memory' => [],
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]],
                ],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 12,
                'candidatesTokenCount' => 8,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        '',
    ]);
}

function chatTranslationGeminiErrorBody(int $code, string $status, string $message): string
{
    return json_encode([
        'error' => [
            'code' => $code,
            'message' => $message,
            'status' => $status,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function chatTranslationRequestedModel(Request $request): string
{
    preg_match('#/models/([^:]+):#', (string) $request->url(), $matches);

    return $matches[1] ?? 'unknown';
}
