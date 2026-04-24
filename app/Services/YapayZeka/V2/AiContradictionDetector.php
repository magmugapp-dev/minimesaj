<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiMemory;
use App\Services\YapayZeka\V2\Data\AiTurnContext;

class AiContradictionDetector
{
    private const SURFACE_CONFIDENCE = 0.72;
    private const KEY_PRIORITIES = [
        'location_city' => 120,
        'location_country' => 118,
        'location_region' => 116,
        'job_current' => 114,
        'job_sector' => 108,
        'education_school' => 106,
        'education_department' => 104,
        'education_level' => 102,
        'relationship_status' => 100,
        'identity_nickname' => 98,
        'identity_nationality' => 96,
        'culture_origin' => 94,
        'language_primary' => 92,
        'goal_current' => 84,
        'boundary_current' => 82,
        'pet_current' => 80,
        'family_siblings_count' => 78,
    ];

    public function detect(AiTurnContext $context, array $candidate): ?array
    {
        if (($candidate['validity'] ?? 'stable') !== 'stable') {
            return null;
        }

        $key = $candidate['key'] ?? null;
        $normalizedValue = $candidate['normalized_value'] ?? null;
        $confidence = (float) ($candidate['confidence'] ?? 0.75);

        if (!$key || !$normalizedValue) {
            return null;
        }

        $memory = AiMemory::query()
            ->forCounterpart(
                $context->aiUser->id,
                $context->hedefTipi(),
                $context->hedefId(),
                $context->kanal,
            )
            ->where('anahtar', $key)
            ->whereNotNull('normalize_deger')
            ->latest('son_goruldu_at')
            ->latest('updated_at')
            ->first();

        if (!$memory || $this->sameValue((string) $memory->normalize_deger, (string) $normalizedValue)) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $this->labelFor($key),
            'type' => $candidate['type'] ?? $memory->hafiza_tipi,
            'previous_value' => $memory->deger ?: $memory->icerik,
            'previous_normalized_value' => $memory->normalize_deger,
            'new_value' => $candidate['value'] ?? $candidate['content'] ?? null,
            'new_normalized_value' => $normalizedValue,
            'importance' => (int) ($candidate['importance'] ?? $memory->onem_puani ?? 5),
            'priority' => $this->priorityFor($key),
            'confidence' => $confidence,
            'should_surface' => $confidence >= self::SURFACE_CONFIDENCE,
            'source_memory_id' => $memory->id,
        ];
    }

    private function sameValue(string $old, string $new): bool
    {
        $old = trim($old);
        $new = trim($new);

        if ($old === $new) {
            return true;
        }

        if ($old === '' || $new === '') {
            return false;
        }

        $compactOld = str_replace([' ', '-', '.'], '', $old);
        $compactNew = str_replace([' ', '-', '.'], '', $new);

        return str_contains($compactOld, $compactNew) || str_contains($compactNew, $compactOld);
    }

    private function priorityFor(string $key): int
    {
        return self::KEY_PRIORITIES[$key] ?? 70;
    }

    private function labelFor(string $key): string
    {
        return match ($key) {
            'location_city' => 'yasadigin sehir',
            'location_country' => 'yasadigin ulke',
            'location_region' => 'yasadigin bolge',
            'job_current' => 'meslegin',
            'job_sector' => 'sektorun',
            'education_school' => 'okulun',
            'education_department' => 'bolumun',
            'education_level' => 'egitim seviyen',
            'relationship_status' => 'iliski durumun',
            'identity_nickname' => 'kendini tanitma seklin',
            'identity_nationality' => 'uyrugun',
            'culture_origin' => 'kulturel kokenin',
            'language_primary' => 'ana dilin',
            'goal_current' => 'hedefin',
            'boundary_current' => 'sinirin',
            'pet_current' => 'evcil hayvan bilgin',
            'family_siblings_count' => 'kardes sayin',
            default => str_replace('_', ' ', $key),
        };
    }
}
