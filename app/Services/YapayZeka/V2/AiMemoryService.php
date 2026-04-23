<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiHafiza;
use App\Models\AiMemory;
use App\Services\YapayZeka\V2\Data\AiConversationStateSnapshot;
use App\Services\YapayZeka\V2\Data\AiInterpretation;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiMemoryService
{
    public function __construct(
        private ?AiMemoryExtractor $extractor = null,
        private ?AiContradictionDetector $contradictionDetector = null,
        private ?AiMemoryNormalizer $normalizer = null,
    ) {
        $this->extractor ??= app(AiMemoryExtractor::class);
        $this->contradictionDetector ??= app(AiContradictionDetector::class);
        $this->normalizer ??= app(AiMemoryNormalizer::class);
    }

    public function recall(AiTurnContext $context, int $limit = 8): Collection
    {
        return AiMemory::query()
            ->forCounterpart(
                $context->aiUser->id,
                $context->hedefTipi(),
                $context->hedefId(),
                $context->kanal,
            )
            ->aktif()
            ->orderByDesc('onem_puani')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function markUsed(Collection $memories): void
    {
        if ($memories->isEmpty()) {
            return;
        }

        AiMemory::query()
            ->whereIn('id', $memories->pluck('id'))
            ->update(['son_kullanildi_at' => now()]);
    }

    public function extractCandidates(
        AiTurnContext $context,
        string $userText,
        AiInterpretation $interpretation,
        AiConversationStateSnapshot $state,
    ): array {
        return $this->extractor
            ->extract($context, $userText, $interpretation, $state)['candidates'] ?? [];
    }

    public function analyzeIncoming(
        AiTurnContext $context,
        string $userText,
        AiInterpretation $interpretation,
        AiConversationStateSnapshot $state,
    ): array {
        $extraction = $this->extractor->extract($context, $userText, $interpretation, $state);
        $storeResult = $this->storeCandidates($context, $extraction['candidates'] ?? []);

        return [
            'extraction' => $extraction,
            'stored' => $storeResult['stored'],
            'contradictions' => $storeResult['contradictions'],
        ];
    }

    public function storeCandidates(AiTurnContext $context, array $candidates): array
    {
        $stored = [];
        $contradictions = [];

        foreach ($candidates as $candidate) {
            $candidate = $this->normalizeCandidate($candidate);
            if ($candidate === null) {
                continue;
            }

            $contradiction = $this->contradictionDetector->detect($context, $candidate);
            if ($contradiction !== null) {
                $contradictions[] = $contradiction;
            }

            $query = AiMemory::query()
                ->forCounterpart(
                    $context->aiUser->id,
                    $context->hedefTipi(),
                    $context->hedefId(),
                    $context->kanal,
                )
                ->where('hafiza_tipi', $candidate['type'])
                ->where('anahtar', $candidate['key'] ?? null);

            $memory = $query->first();
            $isNew = !$memory;
            $memory ??= new AiMemory([
                'ai_user_id' => $context->aiUser->id,
                'kanal' => $context->kanal,
                'hedef_tipi' => $context->hedefTipi(),
                'hedef_id' => $context->hedefId(),
                'hafiza_tipi' => $candidate['type'],
                'anahtar' => $candidate['key'] ?? null,
            ]);

            $metadata = $memory->metadata ?: [];
            if ($contradiction !== null) {
                $metadata['previous_values'] = array_values(array_merge(
                    $metadata['previous_values'] ?? [],
                    [[
                        'value' => $contradiction['previous_value'] ?? null,
                        'normalized_value' => $contradiction['previous_normalized_value'] ?? null,
                        'replaced_at' => now()->toISOString(),
                        'source_memory_id' => $contradiction['source_memory_id'] ?? null,
                    ]]
                ));
            }

            $fill = [
                'icerik' => $candidate['content'],
                'onem_puani' => (int) ($candidate['importance'] ?? 5),
                'kaynak_mesaj_id' => $context->gelenMesaj?->id,
                'kaynak_instagram_mesaj_id' => $context->instagramMesaj?->id,
                'son_kullanma_tarihi' => $candidate['expires_at'] ?? null,
                'metadata' => $metadata,
            ];

            if (Schema::hasColumn('ai_memories', 'deger')) {
                $fill['deger'] = $candidate['value'] ?? null;
            }
            if (Schema::hasColumn('ai_memories', 'normalize_deger')) {
                $fill['normalize_deger'] = $candidate['normalized_value'] ?? null;
            }
            if (Schema::hasColumn('ai_memories', 'guven_skoru')) {
                $fill['guven_skoru'] = $candidate['confidence'] ?? null;
            }
            if (Schema::hasColumn('ai_memories', 'gecerlilik_tipi')) {
                $fill['gecerlilik_tipi'] = $candidate['validity'] ?? 'stable';
            }
            if (Schema::hasColumn('ai_memories', 'ilk_goruldu_at') && $isNew) {
                $fill['ilk_goruldu_at'] = now();
            }
            if (Schema::hasColumn('ai_memories', 'son_goruldu_at')) {
                $fill['son_goruldu_at'] = now();
            }

            $memory->fill($fill)->save();
            $stored[] = $memory->id;

            $this->mirrorLegacyMemory($context, $candidate);
        }

        return [
            'stored' => $stored,
            'contradictions' => $contradictions,
        ];
    }

    private function normalizeCandidate(array $candidate): ?array
    {
        $key = $this->normalizer->key($candidate['key'] ?? null);
        $content = trim((string) ($candidate['content'] ?? ''));
        $value = $this->normalizer->displayValue($candidate['value'] ?? null);
        $normalizedValue = $this->normalizer->value($candidate['normalized_value'] ?? $value);

        if (!$key || ($content === '' && !$value)) {
            return null;
        }

        $validity = ($candidate['validity'] ?? 'stable') === 'volatile' ? 'volatile' : 'stable';

        return array_merge($candidate, [
            'type' => $candidate['type'] ?? AiMemory::TIP_FACT,
            'key' => $key,
            'value' => $value,
            'normalized_value' => $normalizedValue,
            'content' => $content !== '' ? $content : "Kullanici {$key} icin {$value} bilgisini verdi.",
            'importance' => max(1, min(10, (int) ($candidate['importance'] ?? 5))),
            'confidence' => max(0.0, min(1.0, (float) ($candidate['confidence'] ?? 0.7))),
            'validity' => $validity,
        ]);
    }

    private function mirrorLegacyMemory(AiTurnContext $context, array $candidate): void
    {
        if (!Schema::hasTable('ai_hafizalari')) {
            return;
        }

        $legacyType = match ($candidate['type']) {
            AiMemory::TIP_PREFERENCE => AiHafiza::HAFIZA_TIPI_TERCIH,
            AiMemory::TIP_EMOTION => AiHafiza::HAFIZA_TIPI_DUYGU,
            AiMemory::TIP_BOUNDARY => AiHafiza::HAFIZA_TIPI_SINIR,
            AiMemory::TIP_RELATIONSHIP, AiMemory::TIP_SUMMARY => AiHafiza::HAFIZA_TIPI_OZET,
            default => AiHafiza::HAFIZA_TIPI_BILGI,
        };

        AiHafiza::query()->updateOrCreate(
            [
                'ai_user_id' => $context->aiUser->id,
                'hedef_tipi' => $context->hedefTipi(),
                'hedef_id' => $context->hedefId(),
                'konu_anahtari' => $candidate['key'] ?? ('v2_' . md5($candidate['content'])),
            ],
            [
                'sohbet_id' => $context->sohbet?->id,
                'hafiza_tipi' => $legacyType,
                'icerik' => $candidate['content'],
                'onem_puani' => (int) ($candidate['importance'] ?? 5),
                'kaynak_mesaj_id' => $context->gelenMesaj?->id ?? $context->instagramMesaj?->id,
                'son_kullanma_tarihi' => $candidate['expires_at'] ?? null,
            ]
        );
    }
}
