<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiGuardrailRule;
use App\Models\AiPersonaProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiGuardrailService
{
    public function rulesFor(AiPersonaProfile $persona, string $kanal, ?string $ruleType = null): Collection
    {
        $globalRules = $persona->engineConfig?->guardrailRules()
            ->where('aktif_mi', true)
            ->where(function ($query) use ($kanal) {
                $query->whereNull('kanal')->orWhere('kanal', $kanal);
            })
            ->get() ?? collect();

        $personaRules = $persona->guardrailRules()
            ->where('aktif_mi', true)
            ->where(function ($query) use ($kanal) {
                $query->whereNull('kanal')->orWhere('kanal', $kanal);
            })
            ->get();

        $rules = $globalRules->concat($personaRules);

        if ($ruleType === null) {
            return $rules->values();
        }

        return $rules->where('rule_type', $ruleType)->values();
    }

    public function blockedKeywords(AiPersonaProfile $persona, string $kanal): array
    {
        return $this->rulesFor($persona, $kanal, 'blocked_topic')
            ->flatMap(fn (AiGuardrailRule $rule) => $this->splitKeywordPayload($rule->icerik))
            ->map(fn (string $keyword) => Str::lower(trim($keyword)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function requiredInstructions(AiPersonaProfile $persona, string $kanal): array
    {
        return $this->rulesFor($persona, $kanal, 'required_rule')
            ->pluck('icerik')
            ->filter()
            ->values()
            ->all();
    }

    public function detectViolations(string $text, AiPersonaProfile $persona, string $kanal): array
    {
        $normalized = Str::lower($text);
        $matches = [];

        foreach ($this->blockedKeywords($persona, $kanal) as $keyword) {
            if ($keyword !== '' && Str::contains($normalized, $keyword)) {
                $matches[] = $keyword;
            }
        }

        return [
            'blocked' => $matches !== [],
            'matches' => array_values(array_unique($matches)),
        ];
    }

    public function boundaryReply(array $matches, string $kanal): string
    {
        $joined = ' ' . Str::lower(implode(' ', $matches)) . ' ';

        if (Str::contains($joined, [' whatsapp ', ' telegram ', ' telefon ', ' numara ', ' snap '])) {
            return 'Simdilik buradan devam etmeyi tercih ediyorum, biraz daha tanisalim istersen.';
        }

        if (Str::contains($joined, [' iban ', ' papara ', ' havale ', ' para gonder '])) {
            return 'Para veya odeme konularina girmek istemiyorum, baska bir seyden konusalim.';
        }

        if (Str::contains($joined, [' yapay zeka ', ' bot ', ' assistant ', ' model '])) {
            return 'Beni biraz fazla cozmeye calisiyorsun gibi :) Ben burada seni tanimaya odaklandim.';
        }

        return $kanal === 'instagram'
            ? 'Bu konuya girmek istemiyorum, istersen baska bir seyden devam edelim.'
            : 'O konuda rahat hissetmiyorum, istersen baska bir seyden konusalim.';
    }

    private function splitKeywordPayload(string $payload): array
    {
        $parts = preg_split('/[\r\n,|]+/', $payload) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
