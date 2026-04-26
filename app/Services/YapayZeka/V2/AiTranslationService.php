<?php

namespace App\Services\YapayZeka\V2;

use App\Models\Mesaj;
use App\Models\User;
use App\Services\YapayZeka\GeminiModelPolicy;
use App\Services\YapayZeka\GeminiSaglayici;
use App\Support\AiMessageTextSanitizer;
use App\Support\Language;

class AiTranslationService
{
    public function __construct(
        private ?GeminiSaglayici $geminiSaglayici = null,
        private ?AiJsonResponseParser $jsonParser = null,
    ) {
        $this->geminiSaglayici ??= app(GeminiSaglayici::class);
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

        $sourceText = trim((string) ($message->ai_tarafindan_uretildi_mi
            ? AiMessageTextSanitizer::sanitize($message->mesaj_metni)
            : $message->mesaj_metni));
        $sourceCode = Language::normalizeCode($message->dil_kodu);
        $sourceName = $message->dil_adi ?: Language::name($sourceCode);

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
        ], $this->fastTranslationParameters($sourceText));

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
            'model_adi' => $response['model'] ?? GeminiModelPolicy::TERTIARY_MODEL,
            'translated_at' => now()->toISOString(),
        ];

        $translations[$targetCode] = $payload;
        $message->forceFill(['ceviriler' => $translations])->save();

        return $payload;
    }

    private function fastTranslationParameters(string $sourceText): array
    {
        return [
            'model_adi' => GeminiModelPolicy::TERTIARY_MODEL,
            'model_chain' => [
                GeminiModelPolicy::TERTIARY_MODEL,
                GeminiModelPolicy::SECONDARY_MODEL,
            ],
            'per_model_attempt_budgets' => [1, 1],
            'temperature' => 0.0,
            'top_p' => 0.3,
            'max_output_tokens' => $this->translationTokenLimit($sourceText),
            'stream_timeout_seconds' => 10,
            'connect_timeout_seconds' => 3,
        ];
    }

    private function translationTokenLimit(string $sourceText): int
    {
        return mb_strlen($sourceText) <= 280 ? 256 : 512;
    }
}
