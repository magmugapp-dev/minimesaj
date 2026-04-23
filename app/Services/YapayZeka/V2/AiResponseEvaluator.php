<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiPersonaProfile;
use App\Services\YapayZeka\V2\Data\AiResponsePlan;
use Illuminate\Support\Str;

class AiResponseEvaluator
{
    public function evaluate(
        string $replyText,
        AiPersonaProfile $persona,
        AiResponsePlan $plan,
        array $violations = [],
    ): array {
        $normalized = Str::lower(trim($replyText));
        $reasons = [];

        if ($normalized === '') {
            $reasons[] = 'bos_cevap';
        }

        if (($violations['blocked'] ?? false) === true) {
            $reasons[] = 'guardrail';
        }

        if (Str::contains($normalized, ['yapay zeka', 'bot', 'assistant', 'language model'])) {
            $reasons[] = 'ai_ifsa';
        }

        $length = mb_strlen($replyText);
        if ($length > ($persona->mesaj_uzunlugu_max + 40)) {
            $reasons[] = 'fazla_uzun';
        }

        if ($length > 0 && $length < max(8, $plan->minChars - 8)) {
            $reasons[] = 'fazla_kisa';
        }

        if ($this->hasRiskyTone($normalized, $persona, $plan, $violations)) {
            $reasons[] = 'riskli_ton';
        }

        return [
            'accepted' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    public function fallbackReply(AiResponsePlan $plan): string
    {
        return match ($plan->aim) {
            'comfort' => 'Sana iyi gelmeyen bir sey olduysa anlatabilirsin, ben buradayim.',
            'direct_answer' => 'Haklisin, onu daha net anlatayim istersen.',
            'playful_reciprocity' => 'Sen de hic fena degilsin :) biraz daha anlatsana kendini.',
            'warm_opening' => 'Selam, buraya denk gelmene sevindim. Nasilsin?',
            default => 'Tam olarak o konuya girmek istemiyorum, istersen baska bir seyden devam edelim.',
        };
    }

    private function hasRiskyTone(
        string $normalized,
        AiPersonaProfile $persona,
        AiResponsePlan $plan,
        array $violations,
    ): bool {
        $intensity = max(
            (int) ($persona->argo_seviyesi ?? 0),
            (int) ($persona->sarkastiklik_seviyesi ?? 0),
            (int) ($persona->kiskanclik_seviyesi ?? 0),
        );

        if ($intensity < 8 && ($violations['blocked'] ?? false) !== true) {
            return false;
        }

        $aggressiveTokens = [
            'salak',
            'sacma',
            'kes lan',
            'defol',
            'ne alaka',
            'umrumda degil',
            'beni bozma',
            'takintili',
        ];

        $tonRiskli = Str::contains($normalized, $aggressiveTokens)
            || str_contains($normalized, '!!!')
            || str_contains($normalized, '???');

        if (!$tonRiskli) {
            return false;
        }

        return in_array($plan->tone, ['soft', 'careful', 'calm_direct', 'curious'], true)
            || ($violations['blocked'] ?? false) === true;
    }
}
