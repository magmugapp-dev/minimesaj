<?php

use App\Exceptions\AiSaglayiciHatasi;
use App\Models\Ayar;
use App\Services\YapayZeka\GeminiModelPolicy;
use App\Services\YapayZeka\GeminiSaglayici;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Ayar::query()->updateOrCreate(
        ['anahtar' => 'gemini_api_key'],
        [
            'deger' => 'test-gemini-key',
            'grup' => 'api',
            'tip' => 'text',
        ]
    );
});

it('sends the selected concrete model and structured json config to gemini', function () {
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response(geminiStreamBody('Merhaba!'), 200, [
            'Content-Type' => 'text/event-stream',
        ]),
    ]);

    $saglayici = new GeminiSaglayici();
    $sonuc = $saglayici->tamamla([
        ['role' => 'system', 'content' => 'Yalnizca JSON don.'],
        ['role' => 'user', 'content' => 'Selam'],
    ], [
        'model_adi' => GeminiModelPolicy::PRIMARY_MODEL,
        'temperature' => 0.4,
        'top_p' => 0.8,
        'max_output_tokens' => 512,
    ]);

    Http::assertSent(function (Request $request) {
        $veri = $request->data();
        $schema = data_get($veri, 'generationConfig.responseJsonSchema', []);

        return str_contains(
            (string) $request->url(),
            '/v1beta/models/' . GeminiModelPolicy::PRIMARY_MODEL . ':streamGenerateContent?alt=sse&key=test-gemini-key'
        )
            && data_get($veri, 'generationConfig.responseMimeType') === 'application/json'
            && data_get($veri, 'generationConfig.thinkingConfig.thinkingBudget') === GeminiModelPolicy::DEFAULT_THINKING_BUDGET
            && data_get($veri, 'generationConfig.maxOutputTokens') === 512
            && data_get($veri, 'generationConfig.temperature') === 0.4
            && data_get($veri, 'generationConfig.topP') === 0.8
            && data_get($veri, 'system_instruction.parts.0.text') === 'Yalnizca JSON don.'
            && data_get($schema, 'properties.reply.type') === 'string'
            && data_get($schema, 'properties.memory.type') === 'array'
            && data_get($schema, 'properties.memory.items.properties.konu_anahtari.type') === 'string'
            && data_get($schema, 'properties.memory.items.properties.onem_puani.type') === 'integer';
    });

    expect($sonuc['model'])->toBe(GeminiModelPolicy::PRIMARY_MODEL)
        ->and($sonuc['cevap'])->toBe('{"reply":"Merhaba!","memory":[]}');
});

it('converts an explicit zero thinking budget to the pro default for the primary 3.1 model', function () {
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response(geminiStreamBody('Dusunerek cevapladim.'), 200, [
            'Content-Type' => 'text/event-stream',
        ]),
    ]);

    app(GeminiSaglayici::class)->tamamla([
        ['role' => 'user', 'content' => 'Selam'],
    ], [
        'model_adi' => GeminiModelPolicy::PRIMARY_MODEL,
        'thinking_budget' => 0,
    ]);

    Http::assertSent(function (Request $request) {
        return str_contains(
            (string) $request->url(),
            '/v1beta/models/' . GeminiModelPolicy::PRIMARY_MODEL . ':streamGenerateContent'
        )
            && data_get($request->data(), 'generationConfig.thinkingConfig.thinkingBudget') === GeminiModelPolicy::DEFAULT_THINKING_BUDGET;
    });
});

it('omits thinking config for flash models when no positive thinking budget is provided', function () {
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response(geminiStreamBody('Flash cevap hazir.'), 200, [
            'Content-Type' => 'text/event-stream',
        ]),
    ]);

    app(GeminiSaglayici::class)->tamamla([
        ['role' => 'user', 'content' => 'Selam'],
    ], [
        'model_adi' => GeminiModelPolicy::SECONDARY_MODEL,
    ]);

    Http::assertSent(function (Request $request) {
        $generationConfig = data_get($request->data(), 'generationConfig', []);

        return str_contains(
            (string) $request->url(),
            '/v1beta/models/' . GeminiModelPolicy::SECONDARY_MODEL . ':streamGenerateContent'
        )
            && is_array($generationConfig)
            && !array_key_exists('thinkingConfig', $generationConfig);
    });
});

it('uses flash first for auto quality and does not touch pro on success', function () {
    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $model = geminiRequestedModel($request);
        $requests[$model] = ($requests[$model] ?? 0) + 1;

        return Http::response(geminiStreamBody('Flash model hazirim.'), 200, [
            'Content-Type' => 'text/event-stream',
        ]);
    });

    $sonuc = app(GeminiSaglayici::class)->tamamla([
        ['role' => 'user', 'content' => 'Naber?'],
    ], [
        'model_adi' => GeminiModelPolicy::AUTO_QUALITY,
    ]);

    expect($sonuc['model'])->toBe(GeminiModelPolicy::SECONDARY_MODEL)
        ->and($requests[GeminiModelPolicy::SECONDARY_MODEL] ?? 0)->toBe(1)
        ->and(isset($requests[GeminiModelPolicy::PRIMARY_MODEL]))->toBeFalse();
});

it('never sends the auto quality policy token as an outbound gemini model id', function () {
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response(geminiStreamBody('Policy token concrete modele cozuldu.'), 200, [
            'Content-Type' => 'text/event-stream',
        ]),
    ]);

    $sonuc = app(GeminiSaglayici::class)->tamamla([
        ['role' => 'user', 'content' => 'Selam'],
    ], [
        'model_adi' => GeminiModelPolicy::AUTO_QUALITY,
    ]);

    Http::assertSent(function (Request $request) {
        return str_contains(
            (string) $request->url(),
            '/v1beta/models/' . GeminiModelPolicy::SECONDARY_MODEL . ':streamGenerateContent'
        );
    });

    Http::assertNotSent(function (Request $request) {
        return str_contains(
            (string) $request->url(),
            '/v1beta/models/' . GeminiModelPolicy::AUTO_QUALITY . ':streamGenerateContent'
        );
    });

    expect($sonuc['model'])->toBe(GeminiModelPolicy::SECONDARY_MODEL);
});

it('falls back to flash lite after transient flash failures', function () {
    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $model = geminiRequestedModel($request);
        $requests[$model] = ($requests[$model] ?? 0) + 1;

        if ($model === GeminiModelPolicy::SECONDARY_MODEL) {
            return Http::response(geminiErrorBody(503, 'UNAVAILABLE', 'Flash model is overloaded.'), 503);
        }

        return Http::response(geminiStreamBody('Fallback zinciri calisti.'), 200, [
            'Content-Type' => 'text/event-stream',
        ]);
    });

    $sonuc = app(GeminiSaglayici::class)->tamamla([
        ['role' => 'user', 'content' => 'Selam'],
    ], [
        'model_adi' => GeminiModelPolicy::AUTO_QUALITY,
    ]);

    expect($sonuc['model'])->toBe(GeminiModelPolicy::TERTIARY_MODEL)
        ->and($requests[GeminiModelPolicy::SECONDARY_MODEL] ?? 0)->toBe(1)
        ->and($requests[GeminiModelPolicy::TERTIARY_MODEL] ?? 0)->toBe(1)
        ->and(isset($requests[GeminiModelPolicy::PRIMARY_MODEL]))->toBeFalse();
});

it('skips directly to the next 3.1 model when the current model is unsupported', function () {
    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $model = geminiRequestedModel($request);
        $requests[$model] = ($requests[$model] ?? 0) + 1;

        if ($model === GeminiModelPolicy::SECONDARY_MODEL) {
            return Http::response(
                geminiErrorBody(404, 'NOT_FOUND', 'models/' . GeminiModelPolicy::SECONDARY_MODEL . ' is not found or is not supported for generateContent.'),
                404
            );
        }

        return Http::response(geminiStreamBody('Ikinci model devrede.'), 200, [
            'Content-Type' => 'text/event-stream',
        ]);
    });

    $sonuc = app(GeminiSaglayici::class)->tamamla([
        ['role' => 'user', 'content' => 'Selam'],
    ], [
        'model_adi' => GeminiModelPolicy::AUTO_QUALITY,
    ]);

    expect($sonuc['model'])->toBe(GeminiModelPolicy::TERTIARY_MODEL)
        ->and($requests[GeminiModelPolicy::SECONDARY_MODEL] ?? 0)->toBe(1)
        ->and($requests[GeminiModelPolicy::TERTIARY_MODEL] ?? 0)->toBe(1);
});

it('does not fall back on permanent invalid requests', function () {
    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $model = geminiRequestedModel($request);
        $requests[$model] = ($requests[$model] ?? 0) + 1;

        return Http::response(geminiErrorBody(400, 'INVALID_ARGUMENT', 'Invalid request payload.'), 400);
    });

    expect(fn () => app(GeminiSaglayici::class)->tamamla([
        ['role' => 'user', 'content' => 'Selam'],
    ], [
        'model_adi' => GeminiModelPolicy::AUTO_QUALITY,
    ]))->toThrow(AiSaglayiciHatasi::class, 'Invalid request payload');

    expect($requests[GeminiModelPolicy::SECONDARY_MODEL] ?? 0)->toBe(1)
        ->and(isset($requests[GeminiModelPolicy::TERTIARY_MODEL]))->toBeFalse()
        ->and(isset($requests[GeminiModelPolicy::PRIMARY_MODEL]))->toBeFalse();
});

it('includes the attempted 3.1 model chain in the final error message', function () {
    Http::fake(function () {
        return Http::response(geminiErrorBody(503, 'UNAVAILABLE', 'Capacity spike.'), 503);
    });

    try {
        app(GeminiSaglayici::class)->tamamla([
            ['role' => 'user', 'content' => 'Selam'],
        ], [
            'model_adi' => GeminiModelPolicy::AUTO_QUALITY,
        ]);

        $this->fail('GeminiSaglayici should have failed after exhausting the fallback chain.');
    } catch (AiSaglayiciHatasi $e) {
        expect($e->getMessage())->toContain(GeminiModelPolicy::PRIMARY_MODEL)
            ->toContain(GeminiModelPolicy::SECONDARY_MODEL)
            ->toContain(GeminiModelPolicy::TERTIARY_MODEL);

        expect(data_get($e->baglam, 'configured_model'))->toBe(GeminiModelPolicy::AUTO_QUALITY)
            ->and(data_get($e->baglam, 'attempted_models'))->toBe([
                GeminiModelPolicy::SECONDARY_MODEL,
                GeminiModelPolicy::TERTIARY_MODEL,
                GeminiModelPolicy::PRIMARY_MODEL,
            ]);
    }
});

it('adds explicit timeout overrides to provider connection error context', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out.');
    });

    try {
        app(GeminiSaglayici::class)->tamamla([
            ['role' => 'user', 'content' => 'Selam'],
        ], [
            'model_adi' => GeminiModelPolicy::TERTIARY_MODEL,
            'stream_timeout_seconds' => 9,
            'connect_timeout_seconds' => 3,
        ]);

        $this->fail('GeminiSaglayici should have failed after a connection exception.');
    } catch (AiSaglayiciHatasi $e) {
        expect(data_get($e->baglam, 'stream_timeout_seconds'))->toBe(9)
            ->and(data_get($e->baglam, 'connect_timeout_seconds'))->toBe(3)
            ->and(data_get($e->baglam, 'attempted_models'))->toBe([
                GeminiModelPolicy::TERTIARY_MODEL,
            ]);
    }
});

it('does not lower the php execution limit while running in console', function () {
    $previousLimit = ini_get('max_execution_time');

    if (function_exists('ini_set')) {
        @ini_set('max_execution_time', '0');
    }

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response(geminiStreamBody('Console limit korunur.'), 200, [
            'Content-Type' => 'text/event-stream',
        ]),
    ]);

    try {
        app(GeminiSaglayici::class)->tamamla([
            ['role' => 'user', 'content' => 'Selam'],
        ], [
            'stream_timeout_seconds' => 35,
        ]);

        expect(ini_get('max_execution_time'))->toBe('0');
    } finally {
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', (string) $previousLimit);
        }
    }
});

function geminiStreamBody(string $reply): string
{
    $splitPoint = max(1, (int) floor(mb_strlen($reply) / 2));
    $firstHalf = mb_substr($reply, 0, $splitPoint);
    $secondHalf = mb_substr($reply, $splitPoint);

    return implode("\n\n", [
        'data: ' . json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => '{"reply":"' . $firstHalf,
                    ]],
                ],
            ]],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'data: ' . json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => $secondHalf . '","memory":[]}',
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

function geminiErrorBody(int $code, string $status, string $message): string
{
    return json_encode([
        'error' => [
            'code' => $code,
            'message' => $message,
            'status' => $status,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function geminiRequestedModel(Request $request): string
{
    preg_match('#/models/([^:]+):#', (string) $request->url(), $matches);

    return $matches[1] ?? 'unknown';
}
