<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiMemory;
use App\Services\YapayZeka\GeminiSaglayici;
use App\Services\YapayZeka\V2\Data\AiConversationStateSnapshot;
use App\Services\YapayZeka\V2\Data\AiInterpretation;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use App\Support\Language;
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
                    'max_output_tokens' => 1600,
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
                    'You are a memory extraction engine for a natural dating/chat AI.',
                    'Extract only information the user says about themselves, their life, their history, their preferences, or their boundaries.',
                    'Return JSON only. Do not answer the user.',
                    'Stable facts include identity/nickname, age/birth, country/city/region, culture/origin, language, nationality, job, sector, school/education, relationship status, family, pets, hobbies, preferences, routines, goals, boundaries, and important life events.',
                    'Volatile notes include temporary mood, what happened today, short-term plans, current tiredness, or passing emotions.',
                    'Use stable when the information should be remembered across future chats. Use volatile when it should expire soon.',
                    'Every item needs type, key, value, content, importance, confidence, and validity.',
                    'Keys must be specific and canonical, for example identity.nickname, age.current, location.city, location.country, culture.origin, language.primary, job.current, job.sector, education.school, relationship.status, family.note, pet.current, hobby.primary, goal.current, boundary.current, life_event.recent, preference.likes.coffee.',
                    'If the same message contains multiple stable facts, extract all of them.',
                    'If the user gives a new value for a stable field, still extract the new value cleanly instead of merging it with the old one.',
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
        $normalizedValue = $this->normalizer->valueForKey($key, $item['normalized_value'] ?? $value);
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
        $normalized = $this->normalizedText($text);

        return array_values(array_filter(array_merge(
            $this->identityCandidates($text, $normalized),
            $this->ageAndBirthCandidates($normalized),
            $this->locationCandidates($text, $normalized),
            $this->languageCandidates($text, $normalized),
            $this->jobAndEducationCandidates($text, $normalized),
            $this->relationshipFamilyAndPetCandidates($text, $normalized),
            $this->preferenceGoalBoundaryCandidates($text, $normalized),
            $this->lifeEventCandidates($text, $normalized),
            $this->volatileCandidates($text, $normalized, $interpretation, $state),
        )));
    }

    private function identityCandidates(string $text, string $normalized): array
    {
        $candidates = [];

        if ($nickname = $this->firstCapturedValue($normalized, [
            '/\b(?:bana|beni)\s+([a-z][a-z\s\'-]{1,40})\s+diye\s+cagir\b/i',
            '/\b(?:call me|you can call me)\s+([a-z][a-z\s\'-]{1,40})\b/i',
        ])) {
            $display = $this->headlineValue($nickname);
            $candidates[] = $this->candidate(
                'fact',
                'identity.nickname',
                $display,
                "Kullanici kendisine {$display} denmesini istedigini belirtti.",
                7,
                0.8,
            );
        }

        if ($name = $this->firstCapturedValue($normalized, [
            '/\b(?:adim|ismim)\s+([a-z][a-z\s\'-]{1,40})\b/i',
            '/\bmy name is\s+([a-z][a-z\s\'-]{1,40})\b/i',
        ])) {
            $display = $this->headlineValue($name);
            if (mb_strlen($display) >= 2) {
                $candidates[] = $this->candidate(
                    'fact',
                    'identity.nickname',
                    $display,
                    "Kullanici kendisini {$display} olarak tanitti.",
                    6,
                    0.72,
                );
            }
        }

        return $candidates;
    }

    private function ageAndBirthCandidates(string $normalized): array
    {
        $candidates = [];

        if (preg_match('/\b(\d{1,2})\s+yasindayim\b/i', $normalized, $matches) === 1
            || preg_match('/\b(?:i am|im|i m|i\'m)\s+(\d{1,2})\b/i', $normalized, $matches) === 1) {
            $candidates[] = $this->candidate(
                'fact',
                'age.current',
                $matches[1],
                "Kullanici yasini {$matches[1]} olarak soyledi.",
                8,
                0.85,
            );
        }

        if (preg_match('/\b(\d{4})\s+dogumluyum\b/i', $normalized, $matches) === 1
            || preg_match('/\bborn\s+in\s+(\d{4})\b/i', $normalized, $matches) === 1) {
            $candidates[] = $this->candidate(
                'fact',
                'birth.year',
                $matches[1],
                "Kullanici dogum yilini {$matches[1]} olarak verdi.",
                7,
                0.82,
            );
        }

        return $candidates;
    }

    private function locationCandidates(string $text, string $normalized): array
    {
        $candidates = [];
        $residenceMarkers = [
            'yasiyorum',
            'oturuyorum',
            'ikamet ediyorum',
            'kaliyorum',
            'live in',
            'living in',
            'based in',
        ];

        foreach ($this->catalogMatches($normalized, 'city', $residenceMarkers) as $city) {
            $candidates[] = $this->candidate(
                'fact',
                'location.city',
                $city,
                "Kullanici yasadigi sehir olarak {$city} bilgisini verdi.",
                8,
                0.84,
            );
        }

        foreach ($this->catalogMatches($normalized, 'region', ['bolgem', 'region', 'yasiyorum', 'oturuyorum', 'live in']) as $region) {
            $candidates[] = $this->candidate(
                'fact',
                'location.region',
                $region,
                "Kullanici yasadigi bolge olarak {$region} bilgisini verdi.",
                7,
                0.78,
            );
        }

        foreach ($this->catalogMatches($normalized, 'country', ['ulkem', 'ulke', 'country', 'yasiyorum', 'live in']) as $country) {
            $candidates[] = $this->candidate(
                'fact',
                'location.country',
                $country,
                "Kullanici yasadigi ulke olarak {$country} bilgisini verdi.",
                7,
                0.76,
            );
        }

        if ($city = $this->firstCapturedValue($normalized, [
            '/\b([a-z][a-z\s\'-]{1,60}?)(?:\'?d[ae]|\'?t[ae]|da|de|ta|te)\s+(?:yasiyorum|oturuyorum|kaliyorum)\b/i',
            '/\b(?:sehrim|yasadigim sehir|city is|my city is)\s+([a-z][a-z\s\'-]{1,60})\b/i',
            '/\b(?:i live in|i am living in|im based in|i am based in)\s+([a-z][a-z\s\'-]{1,60})\b/i',
        ])) {
            $candidates[] = $this->candidate(
                'fact',
                'location.city',
                $this->headlineValue($city),
                "Kullanici yasadigi sehir olarak {$this->headlineValue($city)} bilgisini verdi.",
                8,
                0.82,
            );
        }

        if ($country = $this->firstCapturedValue($normalized, [
            '/\b(?:ulkem|yasadigim ulke|country is|my country is|i am from)\s+([a-z][a-z\s\'-]{1,60})\b/i',
        ])) {
            $countryValue = $this->headlineValue($country);
            $candidates[] = $this->candidate(
                'fact',
                'location.country',
                $countryValue,
                "Kullanici yasadigi ulke olarak {$countryValue} bilgisini verdi.",
                7,
                0.79,
            );
        }

        if ($region = $this->firstCapturedValue($normalized, [
            '/\b(?:bolgem|yasadigim bolge|region is|my region is)\s+([a-z][a-z\s\'-]{1,60})\b/i',
        ])) {
            $regionValue = $this->headlineValue($region);
            $candidates[] = $this->candidate(
                'fact',
                'location.region',
                $regionValue,
                "Kullanici yasadigi bolge olarak {$regionValue} bilgisini verdi.",
                7,
                0.76,
            );
        }

        if ($origin = $this->firstCapturedValue($normalized, [
            '/\b(?:kokenim|etnik kokenim|cultural origin is|ethnically im)\s+([a-z][a-z\s\'-]{1,60})\b/i',
        ])) {
            $originValue = $this->headlineValue($origin);
            $candidates[] = $this->candidate(
                'fact',
                'culture.origin',
                $originValue,
                "Kullanici kulturel kokenini {$originValue} olarak anlatti.",
                7,
                0.76,
            );
        }

        if ($nationality = $this->firstCapturedValue($normalized, [
            '/\b(?:uyrugum|nationality is|my nationality is)\s+([a-z][a-z\s\'-]{1,60})\b/i',
        ])) {
            $nationalityValue = $this->headlineValue($nationality);
            $candidates[] = $this->candidate(
                'fact',
                'identity.nationality',
                $nationalityValue,
                "Kullanici uyrugunu {$nationalityValue} olarak soyledi.",
                7,
                0.74,
            );
        }

        return $candidates;
    }

    private function languageCandidates(string $text, string $normalized): array
    {
        $candidates = [];

        if ($primaryLanguage = $this->firstCapturedValue($normalized, [
            '/\b(?:ana dilim|native language is|my native language is|mother tongue is)\s+([a-z][a-z\s\'-]{1,40})\b/i',
        ])) {
            $languageCode = Language::normalizeCode($primaryLanguage) ?: Language::codeFromName($primaryLanguage);
            $languageName = $this->displayLanguage($languageCode, $primaryLanguage);
            $candidates[] = $this->candidate(
                'fact',
                'language.primary',
                $languageName,
                "Kullanici ana dilinin {$languageName} oldugunu soyledi.",
                7,
                0.8,
            );
        }

        foreach ($this->spokenLanguageCodes($normalized) as $languageCode) {
            $languageName = $this->displayLanguage($languageCode, $languageCode);
            $candidates[] = $this->candidate(
                'fact',
                'language.spoken.' . $languageCode,
                $languageName,
                "Kullanici {$languageName} konustugunu belirtti.",
                6,
                0.74,
            );
        }

        return $candidates;
    }

    private function jobAndEducationCandidates(string $text, string $normalized): array
    {
        $candidates = [];

        if ($job = $this->jobValue($normalized)) {
            $jobValue = $this->headlineValue($job);
            $candidates[] = $this->candidate(
                'fact',
                'job.current',
                $jobValue,
                "Kullanici meslegini {$jobValue} olarak soyledi.",
                8,
                0.82,
            );
        }

        if ($sector = $this->firstCapturedValue($normalized, [
            '/\b(?:sektorum|sektorum|industry is|work in|sektor olarak)\s+([a-z][a-z\s\'-]{2,80})\b/i',
        ])) {
            $sectorValue = $this->headlineValue($sector);
            $candidates[] = $this->candidate(
                'fact',
                'job.sector',
                $sectorValue,
                "Kullanici sektorunu {$sectorValue} olarak soyledi.",
                7,
                0.76,
            );
        }

        if ($school = $this->firstCapturedValue($normalized, [
            '/\b(?:okulum|universitem|university|school)\s+([a-z0-9][a-z0-9\s\.\'-]{2,80})\b/i',
            '/\b(?:i study at|i studied at)\s+([a-z0-9][a-z0-9\s\.\'-]{2,80})\b/i',
        ])) {
            $schoolValue = $this->headlineValue($school);
            $candidates[] = $this->candidate(
                'fact',
                'education.school',
                $schoolValue,
                "Kullanici okul bilgisini {$schoolValue} olarak verdi.",
                7,
                0.78,
            );
        }

        if ($department = $this->firstCapturedValue($normalized, [
            '/\b(?:bolumum|major is|department is|i study)\s+([a-z0-9][a-z0-9\s\.\'-]{2,80})\b/i',
        ])) {
            $departmentValue = $this->headlineValue($department);
            $candidates[] = $this->candidate(
                'fact',
                'education.department',
                $departmentValue,
                "Kullanici egitim bolumunu {$departmentValue} olarak paylasti.",
                6,
                0.72,
            );
        }

        if ($level = $this->educationLevel($normalized)) {
            $candidates[] = $this->candidate(
                'fact',
                'education.level',
                $level,
                "Kullanici egitim seviyesini {$level} olarak anlatti.",
                6,
                0.74,
            );
        }

        return $candidates;
    }

    private function relationshipFamilyAndPetCandidates(string $text, string $normalized): array
    {
        $candidates = [];

        if ($relationship = $this->relationshipValue($normalized)) {
            $candidates[] = $this->candidate(
                'relationship',
                'relationship.status',
                $relationship,
                "Kullanici iliski durumunu {$relationship} olarak belirtti.",
                8,
                0.84,
            );
        }

        if (preg_match('/\b(\d+)\s+kardesim\s+var\b/i', $normalized, $matches) === 1
            || preg_match('/\bi have\s+(\d+)\s+siblings\b/i', $normalized, $matches) === 1) {
            $candidates[] = $this->candidate(
                'relationship',
                'family.siblings_count',
                $matches[1],
                "Kullanici {$matches[1]} kardesi oldugunu soyledi.",
                6,
                0.78,
            );
        }

        if ($this->containsAny($normalized, ['annem', 'babam', 'ailem', 'family', 'my mother', 'my father'])) {
            $candidates[] = $this->candidate(
                'relationship',
                'family.note',
                Str::limit($this->sentenceValue($text), 140, ''),
                'Kullanici ailesiyle ilgili bir bilgi paylasti.',
                6,
                0.72,
            );
        }

        if ($pet = $this->petValue($normalized)) {
            $petValue = $this->headlineValue($pet);
            $candidates[] = $this->candidate(
                'fact',
                'pet.current',
                $petValue,
                "Kullanici bir {$petValue} sahibi oldugunu soyledi.",
                7,
                0.8,
            );
        }

        return $candidates;
    }

    private function preferenceGoalBoundaryCandidates(string $text, string $normalized): array
    {
        $candidates = [];

        if ($hobby = $this->firstCapturedValue($normalized, [
            '/\b(?:hobim|hobilerim|my hobbies are|my hobby is)\s+([a-z0-9][a-z0-9\s,\.\'-]{2,120})\b/i',
        ])) {
            $hobbyValue = $this->cleanListValue($hobby);
            $candidates[] = $this->candidate(
                'preference',
                'hobby.primary',
                $hobbyValue,
                "Kullanici hobilerini {$hobbyValue} olarak anlatti.",
                6,
                0.76,
            );
        }

        if ($like = $this->firstCapturedValue($normalized, [
            '/\b(?:seviyorum|hosuma gidiyor|i love|i like)\s+([a-z0-9][a-z0-9\s\.\'-]{2,80})\b/i',
        ])) {
            $value = $this->cleanListValue($like);
            $keyValue = $this->normalizer->key($value) ?: md5($value);
            $candidates[] = $this->candidate(
                'preference',
                'preference.likes.' . $keyValue,
                $value,
                "Kullanici {$value} sevdigini soyledi.",
                6,
                0.78,
            );
        }

        if ($dislike = $this->firstCapturedValue($normalized, [
            '/\b(?:sevmem|hoslanmam|nefret ederim|i dislike|i hate)\s+([a-z0-9][a-z0-9\s\.\'-]{2,80})\b/i',
        ])) {
            $value = $this->cleanListValue($dislike);
            $keyValue = $this->normalizer->key($value) ?: md5($value);
            $candidates[] = $this->candidate(
                'boundary',
                'preference.dislikes.' . $keyValue,
                $value,
                "Kullanici {$value} sevmedigini belirtti.",
                7,
                0.76,
            );
        }

        if ($routine = $this->firstCapturedValue($normalized, [
            '/\b(?:genelde|her sabah|her aksam|usually|every morning|every evening)\s+([a-z0-9][a-z0-9\s,\.\'-]{2,120})\b/i',
        ])) {
            $value = $this->cleanListValue($routine);
            $candidates[] = $this->candidate(
                'fact',
                'routine.daily',
                $value,
                "Kullanici gunluk rutininden {$value} diye bahsetti.",
                5,
                0.7,
            );
        }

        if ($goal = $this->firstCapturedValue($normalized, [
            '/\b(?:hedefim|amacim|planim|my goal is|i want to)\s+([a-z0-9][a-z0-9\s,\.\'-]{2,120})\b/i',
        ])) {
            $value = $this->cleanListValue($goal);
            $candidates[] = $this->candidate(
                'fact',
                'goal.current',
                $value,
                "Kullanici hedefini {$value} olarak paylasti.",
                7,
                0.76,
            );
        }

        if ($boundary = $this->firstCapturedValue($normalized, [
            '/\b(?:istemiyorum|istemem|rahatsiz olurum|sinirimdir|dont want|i do not want|i am not comfortable with)\s+([a-z0-9][a-z0-9\s,\.\'-]{2,120})\b/i',
        ])) {
            $value = $this->cleanListValue($boundary);
            $candidates[] = $this->candidate(
                'boundary',
                'boundary.current',
                $value,
                "Kullanici bir sinir olarak {$value} bilgisini verdi.",
                8,
                0.78,
            );
        } elseif ($this->containsAny($normalized, [
            'rahatsiz olurum',
            'istemiyorum',
            'istemem',
            'sinirimdir',
            'dont want',
            'not comfortable with',
        ])) {
            $value = Str::limit($this->sentenceValue($text), 140, '');
            $candidates[] = $this->candidate(
                'boundary',
                'boundary.current',
                $value,
                "Kullanici bir sinir olarak {$value} bilgisini verdi.",
                8,
                0.72,
            );
        }

        return $candidates;
    }

    private function lifeEventCandidates(string $text, string $normalized): array
    {
        $events = [
            'tasindim',
            'mezun oldum',
            'bosandim',
            'evlendim',
            'is degistirdim',
            'i moved',
            'i graduated',
            'i got divorced',
            'i got married',
            'i changed jobs',
        ];

        if (!$this->containsAny($normalized, $events)) {
            return [];
        }

        $value = Str::limit($this->sentenceValue($text), 140, '');

        return [
            $this->candidate(
                'fact',
                'life_event.recent',
                $value,
                'Kullanici yakin zamanda yasanmis onemli bir olay paylasti.',
                7,
                0.74,
            ),
        ];
    }

    private function volatileCandidates(
        string $text,
        string $normalized,
        AiInterpretation $interpretation,
        AiConversationStateSnapshot $state,
    ): array {
        $candidates = [];

        if (in_array($interpretation->emotion, ['sad', 'angry', 'playful', 'curious'], true)) {
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

        if ($activity = $this->firstCapturedValue($normalized, [
            '/\b(?:bugun|today|su an|right now)\s+([a-z0-9][a-z0-9\s,\.\'-]{2,120})\b/i',
        ])) {
            $value = $this->cleanListValue($activity);
            $candidates[] = [
                'type' => AiMemory::TIP_SUMMARY,
                'key' => 'activity.today',
                'value' => $value,
                'normalized_value' => $this->normalizer->valueForKey('activity.today', $value),
                'content' => "Kullanici bugune dair {$value} bilgisini verdi.",
                'importance' => 4,
                'confidence' => 0.62,
                'validity' => 'volatile',
                'expires_at' => now()->addDays(2),
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

    private function jobValue(string $normalized): ?string
    {
        if ($value = $this->firstCapturedValue($normalized, [
            '/\b(?:meslegim|isim|job is|work as|i work as|calisiyorum olarak)\s+([a-z][a-z\s\'-]{2,80})\b/i',
        ])) {
            return $value;
        }

        $knownJobs = array_merge(
            array_map(
                fn (string $job) => Str::of($job)->lower()->ascii()->toString(),
                config('ai_studio_dropdowns.professions', [])
            ),
            ['lawyer', 'nurse', 'doctor', 'teacher', 'engineer', 'developer', 'designer', 'psychologist']
        );

        foreach ($knownJobs as $job) {
            if (preg_match('/\b' . preg_quote($job, '/') . '(?:yim|im|um|u?m)?\b/i', $normalized) === 1) {
                return $job;
            }
        }

        return null;
    }

    private function educationLevel(string $normalized): ?string
    {
        return match (true) {
            $this->containsAny($normalized, ['doktora', 'phd', 'doctorate']) => 'Doktora',
            $this->containsAny($normalized, ['yuksek lisans', 'masters', 'master']) => 'Yuksek lisans',
            $this->containsAny($normalized, ['lisans', 'bachelor']) => 'Lisans',
            $this->containsAny($normalized, ['on lisans', 'associate degree']) => 'On lisans',
            $this->containsAny($normalized, ['lise', 'high school']) => 'Lise',
            default => null,
        };
    }

    private function relationshipValue(string $normalized): ?string
    {
        return match (true) {
            $this->containsAny($normalized, ['bekarim', 'bekar', 'single']) => 'single',
            $this->containsAny($normalized, ['evliyim', 'evli', 'married']) => 'married',
            $this->containsAny($normalized, ['sevgilim var', 'in a relationship', 'relationship']) => 'in_relationship',
            $this->containsAny($normalized, ['bosandim', 'divorced']) => 'divorced',
            $this->containsAny($normalized, ['karisik', 'complicated']) => 'complicated',
            default => null,
        };
    }

    private function petValue(string $normalized): ?string
    {
        return match (true) {
            $this->containsAny($normalized, ['kedim var', 'i have a cat', 'cat']) => 'Kedi',
            $this->containsAny($normalized, ['kopegim var', 'i have a dog', 'dog']) => 'Kopek',
            $this->containsAny($normalized, ['kusum var', 'bird']) => 'Kus',
            default => null,
        };
    }

    private function spokenLanguageCodes(string $normalized): array
    {
        $codes = [];

        foreach (config('ai_studio_dropdowns.languages', []) as $code => $name) {
            $aliases = array_values(array_filter([
                Language::name($code),
                $name,
                strtoupper($code),
                $code,
            ]));

            foreach ($aliases as $alias) {
                $aliasNormalized = $this->normalizedText($alias);
                if ($aliasNormalized === '') {
                    continue;
                }

                $pattern = '/\b' . preg_quote($aliasNormalized, '/') . '\b/i';
                if (preg_match($pattern, $normalized) !== 1) {
                    continue;
                }

                if ($this->containsAny($normalized, ['konusuyorum', 'speak', 'biliyorum', 'write in', 'yaziyorum', 'understand'])) {
                    $codes[] = $code;
                    break;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    private function displayLanguage(?string $languageCode, string $fallback): string
    {
        if ($languageCode === null) {
            return $this->headlineValue($fallback);
        }

        return match ($languageCode) {
            'tr' => 'Turkce',
            'en' => 'Ingilizce',
            'de' => 'Almanca',
            'fr' => 'Fransizca',
            'es' => 'Ispanyolca',
            'it' => 'Italyanca',
            'pt' => 'Portekizce',
            'nl' => 'Hollandaca',
            'ar' => 'Arapca',
            'ru' => 'Rusca',
            'uk' => 'Ukraynaca',
            'hi' => 'Hintce',
            'ja' => 'Japonca',
            'ko' => 'Korece',
            'zh' => 'Cince',
            default => strtoupper($languageCode),
        };
    }

    private function catalogMatches(string $normalized, string $target, array $markers = []): array
    {
        if ($markers !== [] && !$this->containsAny($normalized, $markers)) {
            return [];
        }

        $catalog = config('ai_studio_dropdowns.location_catalog', []);
        $matches = [];

        foreach ($catalog as $country => $payload) {
            if ($target === 'country' && preg_match('/\b' . preg_quote($this->normalizedText($country), '/') . '\b/i', $normalized) === 1) {
                $matches[] = $country;
            }

            foreach (($payload['regions'] ?? []) as $region => $cities) {
                if ($target === 'region' && preg_match('/\b' . preg_quote($this->normalizedText($region), '/') . '\b/i', $normalized) === 1) {
                    $matches[] = $region;
                }

                if ($target !== 'city') {
                    continue;
                }

                foreach ($cities as $city) {
                    if (preg_match('/\b' . preg_quote($this->normalizedText($city), '/') . '\b/i', $normalized) === 1) {
                        $matches[] = $city;
                    }
                }
            }
        }

        return array_values(array_unique($matches));
    }

    private function firstCapturedValue(string $normalized, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches) === 1) {
                return $this->cleanValue($matches[1] ?? '');
            }
        }

        return null;
    }

    private function normalizedText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function cleanValue(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/[\.!,\?].*$/u', '')
            ->replaceMatches('/\s+(ama|but|ve|and|sonra|then)\s+.*$/iu', '')
            ->trim(" \t\n\r\0\x0B'\"")
            ->squish()
            ->toString();
    }

    private function cleanListValue(string $value): string
    {
        return Str::of($this->cleanValue($value))
            ->replaceMatches('/\s*,\s*/', ', ')
            ->toString();
    }

    private function headlineValue(string $value): string
    {
        return Str::of($this->cleanValue($value))
            ->headline()
            ->replace(' Ve ', ' ve ')
            ->replace(' De ', ' de ')
            ->replace(' Da ', ' da ')
            ->toString();
    }

    private function sentenceValue(string $value): string
    {
        return Str::of(trim($value))
            ->replaceMatches('/\s+/', ' ')
            ->trim(" \t\n\r\0\x0B'\"")
            ->toString();
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            $normalizedNeedle = $this->normalizedText($needle);
            if ($normalizedNeedle !== '' && str_contains($haystack, $normalizedNeedle)) {
                return true;
            }
        }

        return false;
    }

    private function candidate(string $type, string $key, string $value, string $content, int $importance, float $confidence): array
    {
        $normalizedKey = $this->normalizer->key($key) ?? $key;

        return [
            'type' => $this->memoryType($type, 'stable'),
            'key' => $normalizedKey,
            'value' => $value,
            'normalized_value' => $this->normalizer->valueForKey($normalizedKey, $value),
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
            str_contains($normalized, 'preference') || str_contains($normalized, 'tercih') || str_contains($normalized, 'hobby') => AiMemory::TIP_PREFERENCE,
            str_contains($normalized, 'emotion') || str_contains($normalized, 'duygu') => AiMemory::TIP_EMOTION,
            str_contains($normalized, 'relationship') || str_contains($normalized, 'family') || str_contains($normalized, 'iliski') => AiMemory::TIP_RELATIONSHIP,
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
