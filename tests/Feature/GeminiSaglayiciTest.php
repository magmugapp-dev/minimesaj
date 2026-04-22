<?php

use App\Models\Ayar;
use App\Services\YapayZeka\GeminiSaglayici;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends the selected model and structured json config to gemini', function () {
    Ayar::create([
        'anahtar' => 'gemini_api_key',
        'deger' => 'test-gemini-key',
        'grup' => 'api',
        'tip' => 'text',
    ]);

    $streamBody = implode("\n\n", [
        'data: ' . json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => '{"reply":"Merha',
                    ]],
                ],
            ]],
        ], JSON_UNESCAPED_SLASHES),
        'data: ' . json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => 'ba!","memory":[]}',
                    ]],
                ],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 12,
                'candidatesTokenCount' => 8,
            ],
        ], JSON_UNESCAPED_SLASHES),
        '',
    ]);

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response($streamBody, 200, [
            'Content-Type' => 'text/event-stream',
        ]),
    ]);

    $saglayici = new GeminiSaglayici();
    $sonuc = $saglayici->tamamla([
        ['role' => 'system', 'content' => 'Yalnizca JSON don.'],
        ['role' => 'user', 'content' => 'Selam'],
    ], [
        'model_adi' => 'gemini-2.5-flash-preview-09-2025',
        'temperature' => 0.4,
        'top_p' => 0.8,
        'max_output_tokens' => 512,
    ]);

    Http::assertSent(function (Request $request) {
        $veri = $request->data();
        $schema = data_get($veri, 'generationConfig.responseJsonSchema', []);

        return str_contains(
            (string) $request->url(),
            '/v1beta/models/gemini-2.5-flash-preview-09-2025:streamGenerateContent?alt=sse&key=test-gemini-key'
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

    expect($sonuc['model'])->toBe('gemini-2.5-flash-preview-09-2025');
    expect($sonuc['cevap'])->toBe('{"reply":"Merhaba!","memory":[]}');
});
