<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiPersonaProfile;
use App\Services\YapayZeka\V2\Data\AiConversationStateSnapshot;
use App\Services\YapayZeka\V2\Data\AiInterpretation;
use App\Services\YapayZeka\V2\Data\AiResponsePlan;

class AiResponsePlanner
{
    public function plan(
        AiInterpretation $interpretation,
        AiConversationStateSnapshot $state,
        AiPersonaProfile $persona,
        bool $firstMessage = false,
        array $contradictionSignals = [],
    ): AiResponsePlan {
        $hasSurfacedContradiction = collect($contradictionSignals)
            ->contains(fn (array $signal) => (bool) ($signal['should_surface'] ?? false));
        $aim = $hasSurfacedContradiction
            ? 'clarify_memory_consistency'
            : ($firstMessage ? 'warm_opening' : $interpretation->expectation);

        $tone = match (true) {
            $hasSurfacedContradiction => 'curious',
            $interpretation->emotion === 'sad' => 'soft',
            $interpretation->emotion === 'angry' => 'careful',
            $interpretation->emotion === 'flirty' => 'playful',
            $firstMessage => 'warm',
            default => $persona->konusma_tonu ?: 'natural',
        };

        $styleHint = match ($interpretation->intent) {
            'question' => 'once soruya cevap ver, sonra akisi devam ettir',
            'support_seek' => 'rahatlat, yumusak ve empatik kal',
            'conflict' => 'gerilimi dusur, savunmaya gecme',
            'flirt' => 'hafif tatli, abartisiz flortoz',
            default => $persona->konusma_stili ?: 'samimi ve dogal',
        };
        if ($hasSurfacedContradiction) {
            $styleHint = 'once onceki bilgiyle yeni bilgi arasindaki farki insan gibi yumuşakca fark et, sorguya cekmeden tek dogal soru sor';
        }

        $minChars = max(12, (int) $persona->mesaj_uzunlugu_min);
        $maxChars = max($minChars + 20, (int) $persona->mesaj_uzunlugu_max);
        if ($interpretation->energy === 'low') {
            $maxChars = min($maxChars, 120);
        }

        return new AiResponsePlan(
            $aim,
            $tone,
            $minChars,
            $maxChars,
            $hasSurfacedContradiction || $interpretation->emotion !== 'angry',
            (int) $persona->emoji_seviyesi,
            (int) $persona->flort_seviyesi,
            $styleHint,
        );
    }
}
