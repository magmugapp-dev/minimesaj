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

        $warmth = $this->score($persona, ['sicaklik_seviyesi', 'empati_seviyesi', 'duygusallik_seviyesi']);
        $directness = $this->score($persona, ['ozguven_seviyesi', 'baskinlik_seviyesi', 'ciddiyet_seviyesi']);
        $playfulness = $this->score($persona, ['mizah_seviyesi', 'oyunculuk_seviyesi', 'sarkastiklik_seviyesi']);
        $curiosity = $this->score($persona, ['merak_seviyesi', 'giriskenlik_seviyesi']);
        $patience = $this->score($persona, ['sabir_seviyesi', 'hassasiyet_seviyesi'], 10 - (int) $persona->utangaclik_seviyesi);
        $romance = $this->score($persona, ['flort_seviyesi', 'romantizm_seviyesi', 'kiskanclik_seviyesi']);
        $energy = $this->score($persona, ['enerji_seviyesi', 'emoji_seviyesi'], (int) $state->enerjiPuani / 10);

        $tone = $this->resolveTone(
            $interpretation,
            $firstMessage,
            $hasSurfacedContradiction,
            $warmth,
            $directness,
            $playfulness,
            $romance,
            $energy,
            $persona,
        );

        $styleHint = $this->buildStyleHint(
            $interpretation,
            $hasSurfacedContradiction,
            $warmth,
            $directness,
            $playfulness,
            $curiosity,
            $patience,
            $romance,
            $persona,
        );

        $minChars = max(12, (int) $persona->mesaj_uzunlugu_min);
        $maxChars = max($minChars + 20, (int) $persona->mesaj_uzunlugu_max);

        $minChars += (int) round(($warmth - 5) * 2 + ($directness - 5));
        $maxChars += (int) round(($warmth - 5) * 6 + ($curiosity - 5) * 4 + ($energy - 5) * 3);

        if ($interpretation->energy === 'low') {
            $maxChars = min($maxChars, 140);
        }

        if ($interpretation->emotion === 'angry') {
            $maxChars = min($maxChars, 150);
        }

        if ($firstMessage) {
            $minChars = min($minChars, 36);
            $maxChars = min($maxChars, 170);
        }

        $minChars = max(12, min($minChars, 220));
        $maxChars = max($minChars + 16, min($maxChars, 420));

        $askQuestion = $hasSurfacedContradiction
            || (
                $interpretation->emotion !== 'angry'
                && $interpretation->riskLevel !== 'high'
                && $curiosity >= 4.5
                && $patience >= 4
            );

        return new AiResponsePlan(
            $aim,
            $tone,
            $minChars,
            $maxChars,
            $askQuestion,
            (int) $persona->emoji_seviyesi,
            (int) $persona->flort_seviyesi,
            $styleHint,
        );
    }

    private function resolveTone(
        AiInterpretation $interpretation,
        bool $firstMessage,
        bool $hasSurfacedContradiction,
        float $warmth,
        float $directness,
        float $playfulness,
        float $romance,
        float $energy,
        AiPersonaProfile $persona,
    ): string {
        return match (true) {
            $hasSurfacedContradiction => 'curious',
            $interpretation->emotion === 'sad' && $warmth >= 5 => 'soft',
            $interpretation->emotion === 'angry' => $directness >= 6 ? 'calm_direct' : 'careful',
            $interpretation->emotion === 'flirty' && $romance >= 5 => 'playful',
            $firstMessage && $warmth >= 6 => 'warm',
            $playfulness >= 7 && $energy >= 6 => 'playful',
            $directness >= 7 => 'direct',
            $warmth >= 7 => 'warm',
            default => $persona->konusma_tonu ?: 'natural',
        };
    }

    private function buildStyleHint(
        AiInterpretation $interpretation,
        bool $hasSurfacedContradiction,
        float $warmth,
        float $directness,
        float $playfulness,
        float $curiosity,
        float $patience,
        float $romance,
        AiPersonaProfile $persona,
    ): string {
        if ($hasSurfacedContradiction) {
            return 'Once onceki bilgiyle yeni bilgi arasindaki farki insan gibi yumusakca fark et, sorguya cekmeden tek dogal soru sor.';
        }

        $segments = [];

        $segments[] = match ($interpretation->intent) {
            'question' => 'Once soruya net cevap ver, sonra akisi canli tut.',
            'support_seek' => 'Rahatlat, duyguyu gor ve yumusak kal.',
            'conflict' => 'Gerilimi dusur, savunmaya gecme ve daha dikkatli ilerle.',
            'flirt' => 'Hafif cekim kur ama sohbeti yapaylastirma.',
            default => 'Cevabi tek niyette tut ve sohbet akisini bozma.',
        };

        $segments[] = $warmth >= 7
            ? 'Sicak, sahici ve onaylayici ol.'
            : ($warmth <= 3 ? 'Biraz daha olculu ve mesafeli kal.' : 'Dengeli bir yakinlik koru.');

        $segments[] = $directness >= 7
            ? 'Sozunu dolandirma, ama sertlesme.'
            : ($directness <= 3 ? 'Yumusak gecislerle ilerle.' : 'Net ama nazik bir dille cevap ver.');

        if ($playfulness >= 6) {
            $segments[] = 'Dogal bir oyunbazlik ve hafif mizah tasiyabilirsin.';
        }

        if ($romance >= 6 && $interpretation->intent === 'flirt') {
            $segments[] = 'Romantik ima olabilir ama merkezde karsi tarafin rahatligi kalsin.';
        }

        if ($curiosity >= 7) {
            $segments[] = 'Merak duygusunu hissettir ama arka arkaya sorgulama yapma.';
        }

        if ($patience <= 3) {
            $segments[] = 'Cok uzatma, ritmi hizli tut.';
        } elseif ($patience >= 7) {
            $segments[] = 'Karsi tarafin ritmine uyumlanip acele etme.';
        }

        if ($persona->konusma_stili) {
            $segments[] = 'Genel stil: ' . $persona->konusma_stili . '.';
        }

        return implode(' ', $segments);
    }

    private function score(AiPersonaProfile $persona, array $fields, ?float $extra = null): float
    {
        $values = collect($fields)
            ->map(fn (string $field) => (float) ($persona->{$field} ?? 5));

        if ($extra !== null) {
            $values->push($extra);
        }

        return round($values->avg() ?: 5, 2);
    }
}
