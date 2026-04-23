<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiMemory;
use App\Services\YapayZeka\GeminiSaglayici;
use App\Services\YapayZeka\V2\Data\AiConversationStateSnapshot;
use App\Services\YapayZeka\V2\Data\AiInterpretation;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AiMemoryExtractor
{
    public function __construct(
        private ?GeminiSaglayici $geminiSaglayici = null,
        private ?AiEngineConfigService $engineConfigService = null,
        private ?AiJsonResponseParser $jsonParser = null,
        private ?AiMemoryNormalizer $normalizer = null,
    ) {
        $this->geminiSaglayici ??= app(GeminiSaglayici::class);
        $this->engineConfigService ??= app(AiEngineConfigService::class);
        $this->jsonParser ??= app(AiJsonResponseParser::class);
        $this->normalizer ??= app(AiMemoryNormalizer::class);
    }

    public function extract(
        AiTurnContext $context,
        string $userText,
        AiInterpretation $interpretation,
        AiConversationStateSnapshot $state,
    ): array {
        $userText = trim($userText);
        if ($userText === '') {
            return ['candidates' => [], 'provider' => 'none', 'raw' => null];
        }

        $providerCandidates = [];
        $raw = null;
        $provider = 'fallback';

        try {
            $config = $this->engineConfigService->activeConfig();
            $response = $this->geminiSaglayici->tamamlaStream(
                $this->buildMessages($context, $userText, $interpretation, $state),
                array_merge($this->engineConfigService->modelParameters($config), [
                    'temperature' => 0.15,
                    'top_p' => 0.7,
                    'max_output_tokens' => 1400,
                    'response_json_schema' => $this->schema(),
                ]),
            );
            $raw = (string) ($response['cevap'] ?? '');
            $decoded = $this->jsonParser->decode($raw);

            if (is_array($decoded)) {
                $providerCandidates = $this->candidatesFromProviderPayload($decoded);
                $provider = 'gemini';
            }
        } catch (\Throwable) {
            $providerCandidates = [];
        }

        $fallbackCandidates = $this->deterministicCandidates($userText, $interpretation, $state);
        $candidates = $this->deduplicate(array_merge($providerCandidates, $fallbackCandidates));

        return [
            'candidates' => $candidates,
            'provider' => $provider,
            'raw' => $raw,
        ];
    }

    private function buildMessages(
        AiTurnContext $context,
        string $userText,
        AiInterpretation $interpretation,
        AiConversationStateSnapshot $state,
    ): array {
        return [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You are a memory extraction engine for a dating/chat AI.',
                    'Extract only information the user says about themselves or their boundaries.',
                    'Return JSON only. Do not answer the user.',
                    'Stable facts include identity/nickname, age/birth, country/city/region, culture/origin, language, job, school/education, relationship status, family, pets, hobbies, preferences, routines, goals, boundaries, and important life events.',
                    'Volatile notes include temporary mood, what happened today, short-term plans, current tiredness, or passing emotions.',
                    'Use stable when the information can be remembered across future chats. Use volatile when it should expire soon.',
                    'Every item needs type, key, value, content, importance, confidence, and validity.',
                    'Keys must be specific and stable, for example location.city, job.current, education.school, relationship.status, pet.has, preference.likes.coffee.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'channel' => $context->kanal,
                    'message' => $userText,
                    'interpretation' => $interpretation->toArray(),
                    'state' => $state->toArray(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
    }

    private function candidatesFromProviderPayload(array $payload): array
    {
        $items = array_merge(
            Arr::wrap($payload['stable_facts'] ?? []),
            Arr::wrap($payload['volatile_notes'] ?? []),
        );

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => $this->normalizeCandidate($item))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeCandidate(array $item): ?array
    {
        $key = $this->normalizer->key($item['key'] ?? null);
        $value = $this->normalizer->displayValue($item['value'] ?? null);
        $normalizedValue = $this->normalizer->value($item['normalized_value'] ?? $value);
        $content = trim((string) ($item['content'] ?? ''));

        if (!$key || (!$value && $content === '')) {
            return null;
        }

        $validity = ($item['validity'] ?? $item['gecerlilik_tipi'] ?? 'stable') === 'volatile'
            ? 'volatile'
            : 'stable';
        $type = $this->memoryType((string) ($item['type'] ?? $item['hafiza_tipi'] ?? 'fact'), $validity);
        $importance = max(1, min(10, (int) ($item['importance'] ?? $item['onem_puani'] ?? 6)));
        $confidence = max(0.0, min(1.0, (float) ($item['confidence'] ?? $item['guven_skoru'] ?? 0.75)));

        return [
            'type' => $type,
            'key' => $key,
            'value' => $value,
            'normalized_value' => $normalizedValue,
            'content' => $content !== '' ? $content : "Kullanici {$key} icin {$value} bilgisini verdi.",
            'importance' => $importance,
            'confidence' => $confidence,
            'validity' => $validity,
            'expires_at' => $validity === 'volatile' ? now()->addDays(3) : null,
        ];
    }

    private function deterministicCandidates(
        string $text,
        AiInterpretation $interpretation,
        AiConversationStateSnapshot $state,
    ): array {
        $normalized = Str::lower($text);
        $candidates = [];

        foreach ($this->locationValues($text) as $city) {
            $candidates[] = $this->candidate('fact', 'location.city', $city, "Kullanici yasadigi sehir olarak {$city} bilgisini verdi.", 8, 0.82);
        }

        if (preg_match('/(?:^|\s)(?:i\s*am|i\'m|im)\s+(\d{1,2})(?:\s|$)/iu', $text, $matches)
            || preg_match('/\b(\d{1,2})\s+ya[şs][ıi]nday[ıi]m\b/iu', $text, $matches)) {
            $candidates[] = $this->candidate('fact', 'age.current', $matches[1], "Kullanici yasini {$matches[1]} olarak soyledi.", 8, 0.85);
        }

        if ($job = $this->jobValue($text)) {
            $candidates[] = $this->candidate('fact', 'job.current', $job, "Kullanici meslegini {$job} olarak soyledi.", 8, 0.82);
        }

        if (preg_match('/(?:okulum|universitem|üniversitem|school|university)\s+([\pL\pN\s\.\'-]{2,80})/iu', $text, $matches)) {
            $school = $this->cleanValue($matches[1]);
            $candidates[] = $this->candidate('fact', 'education.school', $school, "Kullanici okul bilgisini {$school} olarak verdi.", 7, 0.76);
        }

        if (Str::contains($normalized, ['bekarim', 'bekarım', 'single'])) {
            $candidates[] = $this->candidate('relationship', 'relationship.status', 'single', 'Kullanici bekar oldugunu soyledi.', 7, 0.82);
        } elseif (Str::contains($normalized, ['evliyim', 'married'])) {
            $candidates[] = $this->candidate('relationship', 'relationship.status', 'married', 'Kullanici evli oldugunu soyledi.', 7, 0.82);
        } elseif (Str::contains($normalized, ['sevgilim var', 'in a relationship'])) {
            $candidates[] = $this->candidate('relationship', 'relationship.status', 'in_relationship', 'Kullanici iliskisi oldugunu soyledi.', 7, 0.82);
        }

        if (preg_match('/(?:seviyorum|love|like)\s+([\pL\pN\s\.\'-]{2,80})/iu', $text, $matches)) {
            $value = $this->cleanValue($matches[1]);
            $keyValue = $this->normalizer->key($value) ?: md5($value);
            $candidates[] = $this->candidate('preference', 'preference.likes.' . $keyValue, $value, "Kullanici {$value} sevdigini soyledi.", 6, 0.78);
        }

        if (Str::contains($normalized, ['hoslanmam', 'hoşlanmam', 'sevmem', 'rahatsiz olurum', 'rahatsız olurum'])) {
            $candidates[] = $this->candidate('boundary', 'boundary.dislikes_or_discomfort', Str::limit($text, 120, ''), 'Kullanici bir konuda hoslanmama veya rahatsizlik siniri belirtti.', 8, 0.72);
        }

        if (in_array($interpretation->emotion, ['sad', 'angry'], true)) {
            $candidates[] = [
                'type' => AiMemory::TIP_EMOTION,
                'key' => 'mood.recent',
                'value' => $interpretation->emotion,
                'normalized_value' => $interpretation->emotion,
                'content' => 'Son mesaj tonu ' . $interpretation->emotion . ' gorunuyor.',
                'importance' => 5,
                'confidence' => 0.65,
                'validity' => 'volatile',
                'expires_at' => now()->addDays(3),
            ];
        }

        $candidates[] = [
            'type' => AiMemory::TIP_RELATIONSHIP,
            'key' => 'conversation.last_flow',
            'value' => $interpretation->emotion . ':' . ($state->sonKonu ?: 'general'),
            'normalized_value' => $interpretation->emotion . ':' . ($state->sonKonu ?: 'general'),
            'content' => 'Son akista kullanici duygusu ' . $interpretation->emotion . ', konu ' . ($state->sonKonu ?: 'genel sohbet') . '.',
            'importance' => 4,
            'confidence' => 0.6,
            'validity' => 'volatile',
            'expires_at' => now()->addDays(2),
        ];

        return $candidates;
    }

    private function locationValues(string $text): array
    {
        $values = [];

        foreach ([
            '/\b([\pL][\pL\s\.\'-]{1,60}?)(?:\'?[dt][ae]|da|de|ta|te)\s+(?:ya[şs][ıi]yorum|oturuyorum)\b/iu',
            '/\b(?:i live in|i am living in)\s+([\pL\s\.\'-]{2,60})/iu',
            '/\b(?:je vis a|je vis à|vivo en|ich wohne in)\s+([\pL\s\.\'-]{2,60})/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $values[] = $this->cleanValue($matches[1]);
            }
        }

        return array_values(array_unique(array_filter($values)));
    }

    private function jobValue(string $text): ?string
    {
        if (preg_match('/(?:mesle[ğg]im|job is|work as|calisiyorum\s+olarak|çalışıyorum\s+olarak)\s+([\pL\s\.\'-]{2,80})/iu', $text, $matches)) {
            return $this->cleanValue($matches[1]);
        }

        if (preg_match('/\b(hem[şs]ire|avukat|doktor|[öo][ğg]retmen|m[üu]hendis|yazilimci|yazılımcı|lawyer|nurse|doctor|teacher|engineer|developer)(?:yim|im|um|[ıi]m)?\b/iu', $text, $matches)) {
            return $this->cleanValue($matches[1]);
        }

        return null;
    }

    private function cleanValue(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/[\.!,\?].*$/u', '')
            ->replaceMatches('/\s+(ama|but|ve|and|sonra|then)\s+.*$/iu', '')
            ->trim(" \t\n\r\0\x0B'\"")
            ->toString();
    }

    private function candidate(string $type, string $key, string $value, string $content, int $importance, float $confidence): array
    {
        return [
            'type' => $this->memoryType($type, 'stable'),
            'key' => $key,
            'value' => $value,
            'normalized_value' => $this->normalizer->value($value),
            'content' => $content,
            'importance' => $importance,
            'confidence' => $confidence,
            'validity' => 'stable',
            'expires_at' => null,
        ];
    }

    private function memoryType(string $type, string $validity): string
    {
        $normalized = Str::of($type)->lower()->ascii()->toString();

        return match (true) {
            $validity === 'volatile' && str_contains($normalized, 'emotion') => AiMemory::TIP_EMOTION,
            str_contains($normalized, 'preference') || str_contains($normalized, 'tercih') => AiMemory::TIP_PREFERENCE,
            str_contains($normalized, 'emotion') || str_contains($normalized, 'duygu') => AiMemory::TIP_EMOTION,
            str_contains($normalized, 'relationship') || str_contains($normalized, 'iliski') => AiMemory::TIP_RELATIONSHIP,
            str_contains($normalized, 'boundary') || str_contains($normalized, 'sinir') => AiMemory::TIP_BOUNDARY,
            default => AiMemory::TIP_FACT,
        };
    }

    private function deduplicate(array $candidates): array
    {
        $seen = [];
        $result = [];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $key = ($candidate['key'] ?? '') . '|' . ($candidate['normalized_value'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $candidate;
        }

        return $result;
    }

    private function schema(): array
    {
        $item = [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string'],
                'key' => ['type' => 'string'],
                'value' => ['type' => 'string'],
                'normalized_value' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'importance' => ['type' => 'integer'],
                'confidence' => ['type' => 'number'],
                'validity' => ['type' => 'string'],
            ],
            'required' => ['type', 'key', 'value', 'content', 'importance', 'confidence', 'validity'],
        ];

        return [
            'type' => 'object',
            'properties' => [
                'stable_facts' => ['type' => 'array', 'items' => $item],
                'volatile_notes' => ['type' => 'array', 'items' => $item],
                'detected_language_code' => ['type' => 'string'],
                'detected_language_name' => ['type' => 'string'],
            ],
            'required' => ['stable_facts', 'volatile_notes'],
        ];
    }
}
