<?php

namespace App\Services\YapayZeka\V2;

use App\Models\Mesaj;
use App\Models\User;
use App\Services\YapayZeka\GeminiSaglayici;
use App\Support\Language;

class AiTranslationService
{
    public function __construct(
        private ?GeminiSaglayici $geminiSaglayici = null,
        private ?AiEngineConfigService $engineConfigService = null,
        private ?AiJsonResponseParser $jsonParser = null,
    ) {
        $this->geminiSaglayici ??= app(GeminiSaglayici::class);
        $this->engineConfigService ??= app(AiEngineConfigService::class);
        $this->jsonParser ??= app(AiJsonResponseParser::class);
    }

    public function translateIncomingMessage(Mesaj $message, User $viewer): array
    {
        $targetCode = Language::normalizeCode($viewer->dil) ?: 'tr';
        $targetName = Language::name($targetCode, 'Turkish');
        $translations = $message->ceviriler ?: [];

        if (isset($translations[$targetCode]['metin'])) {
            return $translations[$targetCode];
        }

        $sourceText = trim((string) $message->mesaj_metni);
        $sourceCode = Language::normalizeCode($message->dil_kodu);
        $sourceName = $message->dil_adi ?: Language::name($sourceCode);

        $config = $this->engineConfigService->activeConfig();
        $response = $this->geminiSaglayici->tamamlaStream([
            [
                'role' => 'system',
                'content' => 'Translate the user message faithfully. Return only JSON with reply as the translated text and memory as an empty array. Preserve tone, emojis, names, and flirting level. Do not add explanations.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'source_language' => $sourceName ?: $sourceCode ?: 'auto',
                    'target_language' => $targetName,
                    'message' => $sourceText,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ], array_merge($this->engineConfigService->modelParameters($config), [
            'temperature' => 0.1,
            'top_p' => 0.6,
            'max_output_tokens' => 800,
        ]));

        $raw = (string) ($response['cevap'] ?? '');
        $parsed = $this->jsonParser->parseReply($raw);
        $translated = trim($parsed['reply'] ?: $raw);

        $payload = [
            'metin' => $translated,
            'kaynak_dil_kodu' => $sourceCode,
            'kaynak_dil_adi' => $sourceName,
            'hedef_dil_kodu' => $targetCode,
            'hedef_dil_adi' => $targetName,
            'saglayici' => 'gemini',
            'model_adi' => $response['model'] ?? $config->model_adi,
            'translated_at' => now()->toISOString(),
        ];

        $translations[$targetCode] = $payload;
        $message->forceFill(['ceviriler' => $translations])->save();

        return $payload;
    }
}
