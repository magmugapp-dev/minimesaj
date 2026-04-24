<?php

namespace App\Services\YapayZeka;

use App\Contracts\AiSaglayiciInterface;
use App\Exceptions\AiSaglayiciHatasi;
use App\Models\Ayar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiSaglayici implements AiSaglayiciInterface
{
    public const MODEL_ADI = GeminiModelPolicy::AUTO_QUALITY;
    private const GECICI_HATA_KODLARI = [429, 500, 503];
    private const RESPONSE_MIME_TYPE = 'application/json';
    private const STREAM_TIMEOUT_SANIYE = 90;

    public function tamamla(array $mesajlar, array $parametreler = []): array
    {
        $parametreler['model_adi'] = GeminiModelPolicy::normalizeConfiguredModel($parametreler['model_adi'] ?? null);
        return $this->tamamlaStream($mesajlar, $parametreler);
    }

    public function tamamlaStream(
        array $mesajlar,
        array $parametreler = [],
        ?callable $parcaCallback = null
    ): array {
        $configuredModel = GeminiModelPolicy::normalizeConfiguredModel($parametreler['model_adi'] ?? null);
        $apiKey = Ayar::where('anahtar', 'gemini_api_key')->value('deger');

        if (empty($apiKey)) {
            if (app()->environment('testing')) {
                return $this->testingFallbackResponse(
                    $mesajlar,
                    GeminiModelPolicy::defaultConcreteModel(),
                    $parcaCallback
                );
            }

            throw new AiSaglayiciHatasi(
                'Gemini API key tanimlanmamis. Admin panelinden Gemini anahtarini ekleyin.',
                'gemini',
                $configuredModel
            );
        }

        $responseMimeType = $parametreler['response_mime_type'] ?? self::RESPONSE_MIME_TYPE;
        $responseSchema = $parametreler['response_json_schema'] ?? $this->yapilandirilmisYanitSemasi();

        $generationConfig = [
            'temperature' => $parametreler['temperature'] ?? 0.9,
            'topP' => $parametreler['top_p'] ?? 0.95,
            'maxOutputTokens' => $parametreler['max_output_tokens'] ?? 1024,
            'thinkingConfig' => [
                'thinkingBudget' => $parametreler['thinking_budget'] ?? 0,
            ],
        ];

        if ($responseMimeType) {
            $generationConfig['responseMimeType'] = $responseMimeType;
        }

        if (is_array($responseSchema) && $responseSchema !== []) {
            $generationConfig['responseJsonSchema'] = $responseSchema;
        }

        $govde = [
            'contents' => $this->mesajlariDonustur($mesajlar),
            'generationConfig' => $generationConfig,
        ];

        $sistemMesaji = collect($mesajlar)->firstWhere('role', 'system');
        if ($sistemMesaji) {
            $govde['system_instruction'] = [
                'parts' => [['text' => $sistemMesaji['content']]],
            ];
        }

        return $this->modelIleStreamDene($configuredModel, $apiKey, $govde, $parcaCallback);
    }

    public function saglayiciAdi(): string
    {
        return 'gemini';
    }

    private function modelIleStreamDene(
        string $configuredModel,
        string $apiKey,
        array $govde,
        ?callable $parcaCallback = null
    ): array {
        $models = GeminiModelPolicy::concreteModelChain($configuredModel);
        $perModelBudgets = GeminiModelPolicy::perModelAttemptBudgets($configuredModel);
        $maxAttempts = array_sum($perModelBudgets);
        $attemptedModels = [];
        $attemptsPerModel = array_fill(0, count($models), 0);
        $currentModelIndex = 0;
        $attemptIndex = 0;
        $lastError = null;

        while ($currentModelIndex < count($models) && $attemptIndex < $maxAttempts) {
            $model = $models[$currentModelIndex];
            $attemptsPerModel[$currentModelIndex]++;
            $attemptIndex++;
            $attemptedModels[] = $model;

            try {
                $result = $this->tekModelIleStreamDene(
                    $model,
                    $apiKey,
                    $govde,
                    $parcaCallback,
                    $configuredModel,
                    $attemptIndex,
                    $attemptedModels,
                );

                if (count(array_unique($attemptedModels)) > 1 || $configuredModel !== $model) {
                    Log::channel('ai')->info('Gemini model zinciri bir model secimiyle tamamlandi.', [
                        'configured_model' => $configuredModel,
                        'attempted_models' => array_values(array_unique($attemptedModels)),
                        'final_model' => $model,
                        'attempt_index' => $attemptIndex,
                    ]);
                }

                return $result;
            } catch (AiSaglayiciHatasi $e) {
                $lastError = $this->baglamEklenmisHata($e, [
                    'configured_model' => $configuredModel,
                    'attempt_index' => $attemptIndex,
                    'attempted_models' => $attemptedModels,
                ]);

                $decision = $this->hatayiSiniflandir($lastError);
                $currentBudget = $perModelBudgets[$currentModelIndex] ?? 1;
                $sameModelBudgetLeft = $attemptsPerModel[$currentModelIndex] < $currentBudget
                    && $attemptIndex < $maxAttempts;
                $hasNextModel = $currentModelIndex < (count($models) - 1)
                    && $attemptIndex < $maxAttempts;

                if ($decision['action'] === 'retry_same_model' && $sameModelBudgetLeft) {
                    $this->geciciHataBekletVeLogla(
                        $decision['status_code'],
                        $model,
                        $attemptIndex,
                        $lastError->getMessage(),
                        $configuredModel,
                        $attemptedModels,
                        $decision['reason'],
                    );

                    continue;
                }

                if ($decision['action'] === 'next_model' && $hasNextModel) {
                    $this->fallbackModelGecisiniLogla(
                        $configuredModel,
                        $model,
                        $models[$currentModelIndex + 1],
                        $attemptIndex,
                        $attemptedModels,
                        $decision['reason'],
                        $decision['status_code'],
                        $lastError->getMessage(),
                    );
                    $currentModelIndex++;

                    continue;
                }

                if ($decision['action'] === 'retry_same_model' && !$sameModelBudgetLeft && $hasNextModel) {
                    $this->fallbackModelGecisiniLogla(
                        $configuredModel,
                        $model,
                        $models[$currentModelIndex + 1],
                        $attemptIndex,
                        $attemptedModels,
                        'transient_budget_exhausted',
                        $decision['status_code'],
                        $lastError->getMessage(),
                    );
                    $this->uygunsaBekle($this->yenidenDenemeBeklemeSuresi($attemptIndex));
                    $currentModelIndex++;

                    continue;
                }

                throw $this->nihaiHatayiOlustur(
                    $lastError,
                    $configuredModel,
                    $attemptedModels,
                    $decision['reason'],
                );
            }
        }

        throw $this->nihaiHatayiOlustur(
            $lastError,
            $configuredModel,
            $attemptedModels,
            'attempt_budget_exhausted',
        );
    }

    private function tekModelIleStreamDene(
        string $model,
        string $apiKey,
        array $govde,
        ?callable $parcaCallback,
        string $configuredModel,
        int $attemptIndex,
        array $attemptedModels
    ): array {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key={$apiKey}";

        try {
            $yanit = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ])
                ->withOptions([
                    'stream' => true,
                    'read_timeout' => self::STREAM_TIMEOUT_SANIYE,
                ])
                ->timeout(self::STREAM_TIMEOUT_SANIYE)
                ->send('POST', $url, [
                    'json' => $govde,
                ])
                ->throw();

            $streamSonucu = $this->streamYanitiAyikla($yanit, $model, $parcaCallback);
            $cevapMetni = trim($streamSonucu['cevap']);

            if ($cevapMetni === '') {
                $bosCevapYenidenDenenebilir = $this->bosCevapYenidenDenenebilirMi($streamSonucu['son_veri'] ?? []);

                throw new AiSaglayiciHatasi(
                    $this->bosCevapMesajiniOlustur($streamSonucu['son_veri'] ?? [], $model),
                    'gemini',
                    $model,
                    $bosCevapYenidenDenenebilir,
                    $yanit->status(),
                    [
                        'configured_model' => $configuredModel,
                        'attempt_index' => $attemptIndex,
                        'attempted_models' => $attemptedModels,
                        'error_kind' => 'empty_response',
                    ]
                );
            }

            $tokenKullanimi = $streamSonucu['usageMetadata'] ?? [];

            return [
                'cevap' => $cevapMetni,
                'giris_token' => $tokenKullanimi['promptTokenCount'] ?? 0,
                'cikis_token' => $tokenKullanimi['candidatesTokenCount'] ?? 0,
                'model' => $model,
                'configured_model' => $configuredModel,
            ];
        } catch (RequestException $e) {
            $durumKodu = $e->response?->status();

            throw new AiSaglayiciHatasi(
                $this->requestHatasiniOzetle($e),
                'gemini',
                $model,
                $this->durumKoduGeciciMi($durumKodu),
                $durumKodu,
                [
                    'configured_model' => $configuredModel,
                    'attempt_index' => $attemptIndex,
                    'attempted_models' => $attemptedModels,
                    'error_kind' => 'http_request_failed',
                    'response_body' => $this->normalizeResponseBody((string) $e->response?->body()),
                ],
                $e
            );
        } catch (ConnectionException $e) {
            throw new AiSaglayiciHatasi(
                'Gemini baglanti hatasi: ' . Str::limit($e->getMessage(), 240),
                'gemini',
                $model,
                true,
                null,
                [
                    'configured_model' => $configuredModel,
                    'attempt_index' => $attemptIndex,
                    'attempted_models' => $attemptedModels,
                    'error_kind' => 'connection_exception',
                ],
                $e
            );
        } catch (AiSaglayiciHatasi $e) {
            throw $this->baglamEklenmisHata($e, [
                'configured_model' => $configuredModel,
                'attempt_index' => $attemptIndex,
                'attempted_models' => $attemptedModels,
            ]);
        } catch (\Throwable $e) {
            throw new AiSaglayiciHatasi(
                'Gemini stream istegi beklenmeyen sekilde basarisiz oldu: ' . Str::limit($e->getMessage(), 240),
                'gemini',
                $model,
                true,
                null,
                [
                    'configured_model' => $configuredModel,
                    'attempt_index' => $attemptIndex,
                    'attempted_models' => $attemptedModels,
                    'error_kind' => 'unexpected_exception',
                ],
                $e
            );
        }
    }

    private function mesajlariDonustur(array $mesajlar): array
    {
        $contents = [];

        foreach ($mesajlar as $mesaj) {
            if (($mesaj['role'] ?? null) === 'system') {
                continue;
            }

            $contents[] = [
                'role' => ($mesaj['role'] ?? 'user') === 'assistant' ? 'model' : 'user',
                'parts' => [[
                    'text' => $mesaj['content'] ?? '',
                ]],
            ];
        }

        return $contents;
    }

    private function streamYanitiAyikla(
        Response $yanit,
        string $model,
        ?callable $parcaCallback = null
    ): array {
        $govde = $yanit->toPsrResponse()->getBody();
        $hamBuffer = '';
        $birikmisCevap = '';
        $sonVeri = [];
        $usageMetadata = [];

        while (!$govde->eof()) {
            $hamParca = $govde->read(8192);

            if ($hamParca === '') {
                usleep(10000);
                continue;
            }

            $hamBuffer .= str_replace("\r\n", "\n", $hamParca);

            while (($ayracKonumu = strpos($hamBuffer, "\n\n")) !== false) {
                $hamEtkinlik = substr($hamBuffer, 0, $ayracKonumu);
                $hamBuffer = substr($hamBuffer, $ayracKonumu + 2);

                $this->sseEtkinliginiIsle(
                    $hamEtkinlik,
                    $model,
                    $birikmisCevap,
                    $usageMetadata,
                    $sonVeri,
                    $parcaCallback
                );
            }
        }

        if (trim($hamBuffer) !== '') {
            $this->sseEtkinliginiIsle(
                $hamBuffer,
                $model,
                $birikmisCevap,
                $usageMetadata,
                $sonVeri,
                $parcaCallback
            );
        }

        return [
            'cevap' => trim($birikmisCevap),
            'usageMetadata' => $usageMetadata,
            'son_veri' => $sonVeri,
        ];
    }

    private function sseEtkinliginiIsle(
        string $hamEtkinlik,
        string $model,
        string &$birikmisCevap,
        array &$usageMetadata,
        array &$sonVeri,
        ?callable $parcaCallback = null
    ): void {
        $veriSatirlari = [];

        foreach (explode("\n", $hamEtkinlik) as $satir) {
            $satir = trim($satir);

            if ($satir === '' || str_starts_with($satir, ':')) {
                continue;
            }

            if (str_starts_with($satir, 'data:')) {
                $veriSatirlari[] = ltrim(substr($satir, 5));
            }
        }

        if ($veriSatirlari === []) {
            return;
        }

        $hamJson = trim(implode("\n", $veriSatirlari));

        if ($hamJson === '' || $hamJson === '[DONE]') {
            return;
        }

        $veri = json_decode($hamJson, true);

        if (!is_array($veri)) {
            throw new AiSaglayiciHatasi(
                'Gemini stream yaniti JSON olarak ayrisitirilamadi.',
                'gemini',
                $model,
                true,
                null,
                ['ham_event' => Str::limit($hamJson, 240)]
            );
        }

        $parcaMetni = $this->cevapMetniniAyikla($veri);

        if ($parcaMetni !== '') {
            $birikmisCevap .= $parcaMetni;

            if ($parcaCallback) {
                $parcaCallback($parcaMetni, $veri);
            }
        }

        if (isset($veri['usageMetadata']) && is_array($veri['usageMetadata'])) {
            $usageMetadata = $veri['usageMetadata'];
        }

        $sonVeri = $veri;
    }

    private function cevapMetniniAyikla(array $veri): string
    {
        $parts = $veri['candidates'][0]['content']['parts'] ?? [];

        $metin = collect($parts)
            ->map(fn (array $part) => trim((string) ($part['text'] ?? '')))
            ->filter()
            ->implode("\n");

        return trim($metin);
    }

    private function yapilandirilmisYanitSemasi(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reply' => [
                    'type' => 'string',
                    'description' => 'Kullaniciya gidecek nihai mesaj.',
                ],
                'memory' => [
                    'type' => 'array',
                    'description' => 'Karsi taraf hakkinda cikarilan kisa hafiza kayitlari.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'hafiza_tipi' => [
                                'type' => 'string',
                                'description' => 'bilgi, tercih, duygu veya sinir.',
                            ],
                            'konu_anahtari' => [
                                'type' => 'string',
                                'description' => 'Kaydin alt konu anahtari.',
                            ],
                            'icerik' => [
                                'type' => 'string',
                                'description' => 'Kisa hafiza notu.',
                            ],
                            'onem_puani' => [
                                'type' => 'integer',
                                'description' => '1 ile 10 arasinda onem puani.',
                            ],
                        ],
                        'required' => [
                            'hafiza_tipi',
                            'konu_anahtari',
                            'icerik',
                            'onem_puani',
                        ],
                    ],
                ],
            ],
            'required' => ['reply', 'memory'],
        ];
    }

    private function bosCevapMesajiniOlustur(array $veri, string $model): string
    {
        $ilkAday = $veri['candidates'][0] ?? [];
        $finishReason = $ilkAday['finishReason'] ?? 'bilinmiyor';
        $blockReason = $veri['promptFeedback']['blockReason'] ?? null;
        $guvenlik = collect($ilkAday['safetyRatings'] ?? [])
            ->map(fn (array $rating) => ($rating['category'] ?? 'unknown') . ':' . ($rating['probability'] ?? 'unknown'))
            ->implode(', ');

        $parcalar = array_filter([
            'Gemini 200 dondu ama metin uretmedi',
            "model: {$model}",
            "finish_reason: {$finishReason}",
            $blockReason ? "block_reason: {$blockReason}" : null,
            $guvenlik ? "safety: {$guvenlik}" : null,
        ]);

        return implode(' | ', $parcalar);
    }

    private function bosCevapYenidenDenenebilirMi(array $veri): bool
    {
        $ilkAday = $veri['candidates'][0] ?? [];
        $finishReason = $ilkAday['finishReason'] ?? null;
        $blockReason = $veri['promptFeedback']['blockReason'] ?? null;

        if ($blockReason) {
            return false;
        }

        return in_array($finishReason, [null, 'STOP'], true);
    }

    private function requestHatasiniOzetle(RequestException $e): string
    {
        $durumKodu = $e->response?->status();
        $govde = trim((string) $e->response?->body());
        $govde = preg_replace('/\s+/', ' ', $govde ?? '');

        return trim("Gemini istegi basarisiz ({$durumKodu}). " . Str::limit($govde, 240));
    }

    private function geciciHataBekletVeLogla(
        ?int $durumKodu,
        string $model,
        int $deneme,
        string $hataMesaji,
        string $configuredModel,
        array $attemptedModels,
        string $fallbackReason
    ): void {
        $bekleme = $this->yenidenDenemeBeklemeSuresi($deneme);

        Log::channel('ai')->info(
            "Gemini gecici hata (" . ($durumKodu ?? 'n/a') . "), model: {$model}, {$bekleme}s sonra tekrar denenecek.",
            [
                'configured_model' => $configuredModel,
                'attempted_models' => array_values(array_unique($attemptedModels)),
                'attempt_index' => $deneme,
                'status_code' => $durumKodu,
                'fallback_reason' => $fallbackReason,
                'hata' => $hataMesaji,
            ]
        );

        $this->uygunsaBekle($bekleme);
    }

    private function fallbackModelGecisiniLogla(
        string $configuredModel,
        string $fromModel,
        string $toModel,
        int $attemptIndex,
        array $attemptedModels,
        string $fallbackReason,
        ?int $statusCode,
        string $errorMessage,
    ): void {
        Log::channel('ai')->warning('Gemini model fallback devreye alindi.', [
            'configured_model' => $configuredModel,
            'attempted_models' => array_values(array_unique($attemptedModels)),
            'from_model' => $fromModel,
            'to_model' => $toModel,
            'final_model' => $toModel,
            'attempt_index' => $attemptIndex,
            'fallback_reason' => $fallbackReason,
            'status_code' => $statusCode,
            'hata' => $errorMessage,
        ]);
    }

    private function baglamEklenmisHata(AiSaglayiciHatasi $hata, array $ekBaglam): AiSaglayiciHatasi
    {
        return new AiSaglayiciHatasi(
            $hata->getMessage(),
            $hata->saglayici,
            $hata->model,
            $hata->yenidenDenenebilir,
            $hata->durumKodu,
            array_merge($hata->baglam, $ekBaglam),
            $hata->getPrevious()
        );
    }

    private function hatayiSiniflandir(AiSaglayiciHatasi $hata): array
    {
        $statusCode = $hata->durumKodu;
        $responseBody = (string) ($hata->baglam['response_body'] ?? '');
        $haystack = Str::lower($responseBody . ' ' . $hata->getMessage());

        if ($this->modelDesteklenmiyorMu($statusCode, $haystack)) {
            return ['action' => 'next_model', 'reason' => 'model_not_supported', 'status_code' => $statusCode];
        }

        if ($this->authVeyaYetkiHatasiMi($statusCode, $haystack)) {
            return ['action' => 'fail', 'reason' => 'auth_or_permission', 'status_code' => $statusCode];
        }

        if ($this->guvenlikVeyaPolicyHatasiMi($statusCode, $haystack)) {
            return ['action' => 'fail', 'reason' => 'safety_or_policy', 'status_code' => $statusCode];
        }

        if ($this->kaliciIstemciHatasiMi($statusCode, $haystack)) {
            return ['action' => 'fail', 'reason' => 'invalid_request', 'status_code' => $statusCode];
        }

        if ($hata->yenidenDenenebilir || $this->durumKoduGeciciMi($statusCode)) {
            return ['action' => 'retry_same_model', 'reason' => 'transient_error', 'status_code' => $statusCode];
        }

        return ['action' => 'fail', 'reason' => 'provider_error', 'status_code' => $statusCode];
    }

    private function durumKoduGeciciMi(?int $statusCode): bool
    {
        if ($statusCode === null) {
            return false;
        }

        return $statusCode >= 500 || in_array($statusCode, self::GECICI_HATA_KODLARI, true);
    }

    private function modelDesteklenmiyorMu(?int $statusCode, string $haystack): bool
    {
        if (!in_array($statusCode, [400, 404], true)) {
            return false;
        }

        return str_contains($haystack, 'model')
            && (
                str_contains($haystack, 'not found')
                || str_contains($haystack, 'not supported')
                || str_contains($haystack, 'unsupported')
            );
    }

    private function authVeyaYetkiHatasiMi(?int $statusCode, string $haystack): bool
    {
        if (in_array($statusCode, [401, 403], true)) {
            return true;
        }

        return str_contains($haystack, 'permission')
            || str_contains($haystack, 'unauthorized')
            || str_contains($haystack, 'unauthenticated');
    }

    private function guvenlikVeyaPolicyHatasiMi(?int $statusCode, string $haystack): bool
    {
        return str_contains($haystack, 'safety')
            || str_contains($haystack, 'block_reason')
            || str_contains($haystack, 'policy');
    }

    private function kaliciIstemciHatasiMi(?int $statusCode, string $haystack): bool
    {
        if (!in_array($statusCode, [400, 422], true)) {
            return false;
        }

        return !str_contains($haystack, 'not supported')
            && !str_contains($haystack, 'unsupported')
            && !str_contains($haystack, 'not found');
    }

    private function yenidenDenemeBeklemeSuresi(int $attemptIndex): int
    {
        $base = min(8, 2 ** max(0, $attemptIndex - 1));
        $jitter = app()->environment('testing') ? 0 : random_int(0, max(1, $base));

        return min(15, $base + $jitter);
    }

    private function uygunsaBekle(int $seconds): void
    {
        if ($seconds <= 0 || app()->environment('testing')) {
            return;
        }

        sleep($seconds);
    }

    private function normalizeResponseBody(string $body): string
    {
        $body = preg_replace('/\s+/', ' ', trim($body)) ?? trim($body);

        return $body;
    }

    private function nihaiHatayiOlustur(
        ?AiSaglayiciHatasi $lastError,
        string $configuredModel,
        array $attemptedModels,
        string $fallbackReason,
    ): AiSaglayiciHatasi {
        $uniqueModels = array_values(array_unique($attemptedModels));
        $lastModel = $uniqueModels === [] ? GeminiModelPolicy::defaultConcreteModel() : end($uniqueModels);
        $message = trim(implode(' ', array_filter([
            $lastError?->getMessage() ?: 'Gemini tum denemeler tukendi.',
            $uniqueModels === [] ? null : 'Denenen modeller: ' . implode(', ', $uniqueModels) . '.',
        ])));

        return new AiSaglayiciHatasi(
            $message,
            'gemini',
            $lastError?->model ?: $lastModel,
            false,
            $lastError?->durumKodu,
            array_merge($lastError?->baglam ?? [], [
                'configured_model' => $configuredModel,
                'attempted_models' => $uniqueModels,
                'final_model' => $lastModel,
                'fallback_reason' => $fallbackReason,
            ]),
            $lastError?->getPrevious()
        );
    }

    private function testingFallbackResponse(
        array $mesajlar,
        string $model,
        ?callable $parcaCallback = null
    ): array {
        $sonKullaniciMesaji = collect($mesajlar)
            ->reverse()
            ->first(fn (array $mesaj) => ($mesaj['role'] ?? 'user') === 'user');
        $icerik = trim((string) ($sonKullaniciMesaji['content'] ?? ''));

        $reply = str_contains(mb_strtolower($icerik), 'cevir')
            ? 'Test cevirisi hazir.'
            : 'Test cevabi hazir.';
        $cevap = json_encode([
            'reply' => $reply,
            'memory' => [],
            'gecikme' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($parcaCallback) {
            $parcaCallback($cevap, [
                'stream' => false,
                'model' => $model,
            ]);
        }

        return [
            'cevap' => $cevap,
            'giris_token' => 0,
            'cikis_token' => 0,
            'model' => $model,
        ];
    }
}
