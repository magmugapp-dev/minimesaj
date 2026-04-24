<?php

use App\Exceptions\AiSaglayiciHatasi;
use App\Models\Ayar;
use App\Services\YapayZeka\GeminiModelPolicy;
use App\Services\YapayZeka\GeminiSaglayici;
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
            && data_get($veri, 'generationConfig.thinkingConfig.thinkingBudget') === 0
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

it('retries on a transient error and can still succeed on the primary 3.1 model', function () {
    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $model = geminiRequestedModel($request);
        $requests[$model] = ($requests[$model] ?? 0) + 1;

        if ($model === GeminiModelPolicy::PRIMARY_MODEL && $requests[$model] === 1) {
            return Http::response(geminiErrorBody(503, 'UNAVAILABLE', 'High demand right now.'), 503);
        }

        return Http::response(geminiStreamBody('Ikinci denemede hazirim.'), 200, [
            'Content-Type' => 'text/event-stream',
        ]);
    });

    $sonuc = app(GeminiSaglayici::class)->tamamla([
        ['role' => 'user', 'content' => 'Naber?'],
    ], [
        'model_adi' => GeminiModelPolicy::AUTO_QUALITY,
    ]);

    expect($sonuc['model'])->toBe(GeminiModelPolicy::PRIMARY_MODEL)
        ->and($requests[GeminiModelPolicy::PRIMARY_MODEL] ?? 0)->toBe(2)
        ->and(isset($requests[GeminiModelPolicy::SECONDARY_MODEL]))->toBeFalse();
});

it('falls back to the second 3.1 chain model after transient failures exhaust the primary budget', function () {
    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $model = geminiRequestedModel($request);
        $requests[$model] = ($requests[$model] ?? 0) + 1;

        if ($model === GeminiModelPolicy::PRIMARY_MODEL) {
            return Http::response(geminiErrorBody(503, 'UNAVAILABLE', 'Primary model is overloaded.'), 503);
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

    expect($sonuc['model'])->toBe(GeminiModelPolicy::SECONDARY_MODEL)
        ->and($requests[GeminiModelPolicy::PRIMARY_MODEL] ?? 0)->toBe(2)
        ->and($requests[GeminiModelPolicy::SECONDARY_MODEL] ?? 0)->toBe(1);
});

it('skips directly to the next 3.1 model when the current model is unsupported', function () {
    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $model = geminiRequestedModel($request);
        $requests[$model] = ($requests[$model] ?? 0) + 1;

        if ($model === GeminiModelPolicy::PRIMARY_MODEL) {
            return Http::response(
                geminiErrorBody(404, 'NOT_FOUND', 'models/' . GeminiModelPolicy::PRIMARY_MODEL . ' is not found or is not supported for generateContent.'),
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

    expect($sonuc['model'])->toBe(GeminiModelPolicy::SECONDARY_MODEL)
        ->and($requests[GeminiModelPolicy::PRIMARY_MODEL] ?? 0)->toBe(1)
        ->and($requests[GeminiModelPolicy::SECONDARY_MODEL] ?? 0)->toBe(1);
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

    expect($requests[GeminiModelPolicy::PRIMARY_MODEL] ?? 0)->toBe(1)
        ->and(isset($requests[GeminiModelPolicy::SECONDARY_MODEL]))->toBeFalse();
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
                GeminiModelPolicy::PRIMARY_MODEL,
                GeminiModelPolicy::SECONDARY_MODEL,
                GeminiModelPolicy::TERTIARY_MODEL,
            ]);
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
