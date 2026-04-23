<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiEngineConfig;
use App\Models\AiPersonaProfile;
use App\Models\User;
use App\Services\YapayZeka\GeminiSaglayici;
use App\Services\YapayZeka\V2\Channels\AiChannelAdapterInterface;
use App\Services\YapayZeka\V2\Data\AiConversationStateSnapshot;
use App\Services\YapayZeka\V2\Data\AiGenerationResult;
use App\Services\YapayZeka\V2\Data\AiResponsePlan;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use App\Support\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiResponseGenerator
{
    public function __construct(
        private ?GeminiSaglayici $geminiSaglayici = null,
        private ?AiEngineConfigService $engineConfigService = null,
        private ?AiGuardrailService $guardrailService = null,
        private ?AiJsonResponseParser $jsonParser = null,
    ) {
        $this->geminiSaglayici ??= app(GeminiSaglayici::class);
        $this->engineConfigService ??= app(AiEngineConfigService::class);
        $this->guardrailService ??= app(AiGuardrailService::class);
        $this->jsonParser ??= app(AiJsonResponseParser::class);
    }

    public function generate(
        AiTurnContext $context,
        AiChannelAdapterInterface $adapter,
        AiEngineConfig $config,
        AiPersonaProfile $persona,
        AiConversationStateSnapshot $state,
        AiResponsePlan $plan,
        Collection $memories,
        array $contradictionSignals = [],
        array $repairNotes = [],
    ): AiGenerationResult {
        $messages = $this->buildMessages(
            $context,
            $adapter,
            $config,
            $persona,
            $state,
            $plan,
            $memories,
            $contradictionSignals,
            $repairNotes,
        );

        $response = $this->geminiSaglayici->tamamlaStream(
            $messages,
            $this->resolveModelParameters($config, $persona),
        );

        $raw = (string) ($response['cevap'] ?? '');
        $parsed = $this->jsonParser->parseReply($raw);

        return new AiGenerationResult(
            trim($parsed['reply'] ?: $raw),
            $parsed['memory'] ?? [],
            $raw,
            $response['model'] ?? null,
            (int) ($response['giris_token'] ?? 0),
            (int) ($response['cikis_token'] ?? 0),
            Str::limit($messages[0]['content'] ?? '', 1000, '...'),
        );
    }

    private function buildMessages(
        AiTurnContext $context,
        AiChannelAdapterInterface $adapter,
        AiEngineConfig $config,
        AiPersonaProfile $persona,
        AiConversationStateSnapshot $state,
        AiResponsePlan $plan,
        Collection $memories,
        array $contradictionSignals = [],
        array $repairNotes = [],
    ): array {
        $memoryLines = $memories
            ->map(fn ($memory) => '- ' . $memory->icerik)
            ->values()
            ->all();
        $counterpartLines = $adapter->counterpartProfileLines($context);

        $requiredInstructions = $this->guardrailService->requiredInstructions($persona, $context->kanal);
        $languageCode = Language::normalizeCode($persona->ana_dil_kodu) ?: Language::normalizeCode($context->aiUser->dil) ?: 'tr';
        $languageName = $persona->ana_dil_adi ?: Language::name($languageCode, 'Turkish');
        $personaModel = $this->resolvePersonaModel($config, $persona);
        $lengthInstruction = "Yanit uzunlugu hedefi: {$plan->minChars}-{$plan->maxChars} karakter civari.";
        $questionInstruction = $plan->askQuestion
            ? 'Uygunsa dogal sekilde tek bir karsi soru ile akisi canli tut.'
            : 'Bu turda karsi soru sormak zorunda degilsin; gerekirse sade cevap ver.';
        $emojiInstruction = match (true) {
            $plan->emojiLevel >= 7 => 'Emoji kullanimi serbest ama abartma.',
            $plan->emojiLevel >= 3 => 'Emoji kullanirsan en fazla 1 tane kullan.',
            default => 'Emoji kullanma.',
        };
        $flirtInstruction = match (true) {
            $plan->flirtLevel >= 7 => 'Flort tonun sicak olabilir ama tasmadan, dogal kal.',
            $plan->flirtLevel >= 4 => 'Hafif flort olabilir ama merkezde sohbet aksin.',
            default => 'Flort dozunu dusuk tut.',
        };
        $systemParts = array_filter([
            $config->sistem_komutu ?: 'Dogal ve insan gibi sohbet et.',
            'Senin kimligin: ' . $this->personaIdentity($context->aiUser, $persona),
            'Davranis matrisi: ' . $this->behaviorSummary($persona),
            "Cevap dili: {$languageName} ({$languageCode}). Kullanici baska dilde yazsa bile onu anla ama kendi persona ana dilinde cevap ver. Bu dil tercihi genel dil kurallarindan onceliklidir.",
            'Kanal: ' . $context->kanal,
            'Persona model tercihi: ' . $personaModel . '.',
            'Ton: ' . ($persona->konusma_tonu ?: 'dogal') . ', stil: ' . ($persona->konusma_stili ?: 'samimi'),
            'Anlik durum: ruh hali ' . $state->ruhHali . ', samimiyet ' . $state->samimiyetPuani . ', ilgi ' . $state->ilgiPuani . ', guven ' . $state->guvenPuani . ', gerilim ' . $state->gerilimSeviyesi . '.',
            'Bu turdaki amacin: ' . $plan->aim . '. Uslup notu: ' . $plan->styleHint . '.',
            'Plan tonu: ' . $plan->tone . '. ' . $lengthInstruction,
            $questionInstruction,
            $emojiInstruction,
            $flirtInstruction,
            $context->turnType === 'first_message' && $persona->ilk_mesaj_tonu
                ? 'Ilk mesaj tonu: ' . $persona->ilk_mesaj_tonu
                : null,
            $counterpartLines === [] ? null : "Karsi taraf bilgileri:\n- " . implode("\n- ", $counterpartLines),
            $requiredInstructions === [] ? null : "Zorunlu kurallar:\n- " . implode("\n- ", $requiredInstructions),
            $memoryLines === [] ? null : "Hatirladiklarin:\n" . implode("\n", $memoryLines),
            $this->contradictionInstruction($contradictionSignals),
            $repairNotes === [] ? null : "Duzenleme notlari:\n- " . implode("\n- ", $repairNotes),
            'Tek mesajlik yanit uret. JSON, maddeleme, aciklama veya sistem dili kullanma.',
            'Kendini yapay zeka, bot, assistant veya model olarak ifsa etme.',
        ]);

        $messages = [[
            'role' => 'system',
            'content' => implode("\n\n", $systemParts),
        ]];

        $messages = array_merge($messages, $adapter->recentMessages($context));

        if ($context->turnType === 'first_message') {
            $messages[] = [
                'role' => 'user',
                'content' => implode("\n", [
                    'Bu kisiyeyle yeni eslestin ve ilk mesaji sen atacaksin.',
                    'Iyi bir acilis yap; sicak, insan gibi ve rahat hissettirsin.',
                ]),
            ];
        }

        return $messages;
    }

    private function personaIdentity(User $aiUser, AiPersonaProfile $persona): string
    {
        $name = trim(($aiUser->ad ?: '') . ' ' . ($aiUser->soyad ?: ''));
        $summary = trim((string) ($persona->persona_ozeti ?: $aiUser->biyografi ?: 'samimi bir kisi'));

        $details = array_filter([
            $persona->ana_dil_adi ? 'Ana dil: ' . $persona->ana_dil_adi : null,
            $persona->persona_ulke ? 'Ulke: ' . $persona->persona_ulke : null,
            $persona->persona_bolge ? 'Bolge: ' . $persona->persona_bolge : null,
            $persona->persona_sehir ? 'Sehir: ' . $persona->persona_sehir : null,
            $persona->persona_mahalle ? 'Yasam cevresi: ' . $persona->persona_mahalle : null,
            $persona->kulturel_koken ? 'Kulturel koken: ' . $persona->kulturel_koken : null,
            $persona->uyruk ? 'Uyruk: ' . $persona->uyruk : null,
            $persona->yasam_tarzi ? 'Yasam tarzi: ' . $persona->yasam_tarzi : null,
            $persona->meslek ? 'Meslek: ' . $persona->meslek : null,
            $persona->sektor ? 'Sektor: ' . $persona->sektor : null,
            $persona->egitim ? 'Egitim: ' . $persona->egitim : null,
            $persona->okul_bolum ? 'Okul/bolum: ' . $persona->okul_bolum : null,
            $persona->yas_araligi ? 'Yas araligi: ' . $persona->yas_araligi : null,
            $persona->gunluk_rutin ? 'Gunluk rutin: ' . $persona->gunluk_rutin : null,
            $persona->hobiler ? 'Hobiler: ' . $persona->hobiler : null,
            $persona->sevdigi_mekanlar ? 'Sevdigi mekanlar: ' . $persona->sevdigi_mekanlar : null,
            $persona->aile_arkadas_notu ? 'Aile/arkadas notu: ' . $persona->aile_arkadas_notu : null,
            $persona->iliski_gecmisi_tonu ? 'Iliski gecmisi tonu: ' . $persona->iliski_gecmisi_tonu : null,
            $persona->konusma_imzasi ? 'Konusma imzasi: ' . $persona->konusma_imzasi : null,
            $persona->cevap_ritmi ? 'Cevap ritmi: ' . $persona->cevap_ritmi : null,
            $persona->emoji_aliskanligi ? 'Emoji aliskanligi: ' . $persona->emoji_aliskanligi : null,
            $persona->kacinilacak_persona_detaylari ? 'Kacinilacak detaylar: ' . $persona->kacinilacak_persona_detaylari : null,
        ]);

        return trim($name . '. ' . $summary . ($details === [] ? '' : "\n" . implode("\n", $details)));
    }

    private function contradictionInstruction(array $signals): ?string
    {
        $surfaceSignals = collect($signals)
            ->filter(fn (array $signal) => (bool) ($signal['should_surface'] ?? false))
            ->values();

        if ($surfaceSignals->isEmpty()) {
            return null;
        }

        $lines = $surfaceSignals
            ->map(fn (array $signal) => '- ' . ($signal['key'] ?? 'bilgi') . ': once "' . ($signal['previous_value'] ?? '-') . '", simdi "' . ($signal['new_value'] ?? '-') . '"')
            ->all();

        return "Tutarlilik sinyali:\n" . implode("\n", $lines) . "\nBu farki tek mesaj icinde dogalca fark et. Sorgu memuru gibi davranma; insan gibi, yumusak bir merakla sor.";
    }

    private function resolveModelParameters(AiEngineConfig $config, AiPersonaProfile $persona): array
    {
        $parameters = $this->engineConfigService->modelParameters($config);
        $parameters['model_adi'] = $this->resolvePersonaModel($config, $persona);

        return $parameters;
    }

    private function resolvePersonaModel(AiEngineConfig $config, AiPersonaProfile $persona): string
    {
        $allowedModels = array_keys(config('ai_studio_dropdowns.models', []));
        $personaModel = data_get($persona->metadata, 'model_adi');

        if (is_string($personaModel) && in_array($personaModel, $allowedModels, true)) {
            return $personaModel;
        }

        return $config->model_adi ?: 'gemini-2.5-flash';
    }

    private function behaviorSummary(AiPersonaProfile $persona): string
    {
        $labels = collect(config('ai_studio_dropdowns.behavior_sliders', []))
            ->map(fn (array $meta, string $field) => ($meta['label'] ?? $field) . ' ' . (int) ($persona->{$field} ?? ($meta['default'] ?? 5)) . '/10')
            ->implode(', ');

        return $labels !== '' ? $labels : 'Varsayilan davranis dengesi kullan.';
    }
}
