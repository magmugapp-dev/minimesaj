<?php

namespace App\Services\YapayZeka;

use App\Contracts\AiSaglayiciInterface;
use App\Exceptions\AiSaglayiciHatasi;
use App\Models\Ayar;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiSaglayici implements AiSaglayiciInterface
{
    public const MODEL_ADI = 'gemini-2.5-flash';
    private const MAX_DENEME = 2;
    private const GECICI_HATA_KODLARI = [429, 500, 503];
    private const RESPONSE_MIME_TYPE = 'application/json';
    private const STREAM_TIMEOUT_SANIYE = 90;

    public function tamamla(array $mesajlar, array $parametreler = []): array
    {
        // Model adı sabit: gemini-2.5-flash
        $parametreler['model_adi'] = self::MODEL_ADI;
        return $this->tamamlaStream($mesajlar, $parametreler);
    }

    public function tamamlaStream(
        array $mesajlar,
        array $parametreler = [],
        ?callable $parcaCallback = null
    ): array {
        $model = self::MODEL_ADI;
        $apiKey = Ayar::where('anahtar', 'gemini_api_key')->value('deger');
        // Sadece ayarlar tablosundan anahtar okunacak. .env'den okuma kaldırıldı.
        if (empty($apiKey)) {
            throw new AiSaglayiciHatasi(
                'Gemini API key tanimlanmamis. Admin panelinden Gemini anahtarini ekleyin.',
                'gemini',
                self::MODEL_ADI
            );
        }

        $govde = [
            'contents' => $this->mesajlariDonustur($mesajlar),
            'generationConfig' => [
                'temperature' => $parametreler['temperature'] ?? 0.9,
                'topP' => $parametreler['top_p'] ?? 0.95,
                'maxOutputTokens' => $parametreler['max_output_tokens'] ?? 1024,
                'responseMimeType' => self::RESPONSE_MIME_TYPE,
                'responseJsonSchema' => $this->yapilandirilmisYanitSemasi(),
                'thinkingConfig' => [
                    'thinkingBudget' => $parametreler['thinking_budget'] ?? 0,
                ],
            ],
        ];

        $sistemMesaji = collect($mesajlar)->firstWhere('role', 'system');
        if ($sistemMesaji) {
            $govde['system_instruction'] = [
                'parts' => [['text' => $sistemMesaji['content']]],
            ];
        }

        return $this->modelIleStreamDene($model, $apiKey, $govde, $parcaCallback);
    }

    public function saglayiciAdi(): string
    {
        return 'gemini';
    }

    private function modelIleStreamDene(
        string $model,
        string $apiKey,
        array $govde,
        ?callable $parcaCallback = null
    ): array {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key={$apiKey}";
        $sonHata = null;

        for ($deneme = 1; $deneme <= self::MAX_DENEME; $deneme++) {
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
                        ['deneme' => $deneme]
                    );
                }

                $tokenKullanimi = $streamSonucu['usageMetadata'] ?? [];

                return [
                    'cevap' => $cevapMetni,
                    'giris_token' => $tokenKullanimi['promptTokenCount'] ?? 0,
                    'cikis_token' => $tokenKullanimi['candidatesTokenCount'] ?? 0,
                    'model' => $model,
                ];
            } catch (RequestException $e) {
                $durumKodu = $e->response?->status();
                $yenidenDenenebilir = $durumKodu !== null
                    && in_array($durumKodu, self::GECICI_HATA_KODLARI, true);

                $sonHata = new AiSaglayiciHatasi(
                    $this->requestHatasiniOzetle($e),
                    'gemini',
                    $model,
                    $yenidenDenenebilir,
                    $durumKodu,
                    ['deneme' => $deneme]
                );

                if ($deneme < self::MAX_DENEME && $yenidenDenenebilir) {
                    $this->geciciHataBekletVeLogla($durumKodu, $model, $deneme, $sonHata->getMessage());
                    continue;
                }

                throw $sonHata;
            } catch (AiSaglayiciHatasi $e) {
                $sonHata = $e;

                if ($deneme < self::MAX_DENEME && $e->yenidenDenenebilir) {
                    $this->geciciHataBekletVeLogla($e->durumKodu ?? 200, $model, $deneme, $e->getMessage());
                    continue;
                }

                throw $e;
            } catch (\Throwable $e) {
                $sonHata = new AiSaglayiciHatasi(
                    'Gemini stream istegi beklenmeyen sekilde basarisiz oldu: ' . Str::limit($e->getMessage(), 240),
                    'gemini',
                    $model,
                    true,
                    null,
                    ['deneme' => $deneme],
                    $e
                );

                if ($deneme < self::MAX_DENEME) {
                    sleep(1);
                    continue;
                }

                throw $sonHata;
            }
        }

        if ($sonHata instanceof AiSaglayiciHatasi) {
            throw $sonHata;
        }

        throw new AiSaglayiciHatasi(
            "Gemini tum denemeler tukendi, model: {$model}",
            'gemini',
            $model,
            true,
            null,
            ['max_deneme' => self::MAX_DENEME]
        );
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
        int $durumKodu,
        string $model,
        int $deneme,
        string $hataMesaji
    ): void {
        $bekleme = 1;

        Log::channel('ai')->info(
            "Gemini gecici hata ({$durumKodu}), model: {$model}, {$bekleme}s sonra tekrar denenecek.",
            ['deneme' => $deneme, 'hata' => $hataMesaji]
        );

        sleep($bekleme);
    }
}
