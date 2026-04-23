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
}
