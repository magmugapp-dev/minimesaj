<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiMemory;
use App\Services\YapayZeka\V2\Data\AiTurnContext;

class AiContradictionDetector
{
    private const SURFACE_CONFIDENCE = 0.72;

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
            'type' => $candidate['type'] ?? $memory->hafiza_tipi,
            'previous_value' => $memory->deger ?: $memory->icerik,
            'previous_normalized_value' => $memory->normalize_deger,
            'new_value' => $candidate['value'] ?? $candidate['content'] ?? null,
            'new_normalized_value' => $normalizedValue,
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

        return str_contains($old, $new) || str_contains($new, $old);
    }
}
