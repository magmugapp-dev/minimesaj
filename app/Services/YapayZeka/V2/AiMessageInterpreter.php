<?php

namespace App\Services\YapayZeka\V2;

use App\Services\YapayZeka\V2\Data\AiInterpretation;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Illuminate\Support\Str;

class AiMessageInterpreter
{
    public function interpret(string $text, AiTurnContext $context): AiInterpretation
    {
        $normalized = Str::lower(trim($text));
        $intent = $this->intent($normalized, $context);
        $emotion = $this->emotion($normalized);
        $energy = $this->energy($normalized);
        $riskLevel = $this->riskLevel($normalized);
        $expectation = $this->expectation($normalized, $intent, $emotion);
        $topics = $this->topics($normalized);
        $summary = Str::limit(trim($text), 180, '...');

        return new AiInterpretation(
            $intent,
            $emotion,
            $energy,
            $riskLevel,
            $expectation,
            $topics,
            $summary,
        );
    }

    private function intent(string $text, AiTurnContext $context): string
    {
        if ($context->turnType === 'first_message') {
            return 'opening';
        }

        if ($text === '') {
            return 'casual_chat';
        }

        if (Str::contains($text, ['goruselim', 'bulusalim', 'numarani', 'whatsapp', 'telegram'])) {
            return 'off_platform_push';
        }

        if (Str::contains($text, ['ozledim', 'tatlisin', 'guzelsin', 'yakisikli', 'flort'])) {
            return 'flirt';
        }

        if (Str::contains($text, ['neden', 'nasil', 'ne zaman', '?'])) {
            return 'question';
        }

        if (Str::contains($text, ['sinir', 'kizgin', 'sacma', 'of ya', 'yeter'])) {
            return 'conflict';
        }

        if (Str::contains($text, ['uzgunum', 'kotuyum', 'moralim bozuk', 'canim sikkin'])) {
            return 'support_seek';
        }

        if (Str::contains($text, ['merhaba', 'selam', 'hey', 'slm'])) {
            return 'greeting';
        }

        return 'casual_chat';
    }

    private function emotion(string $text): string
    {
        return match (true) {
            Str::contains($text, ['uzgun', 'kotuyum', 'yorgunum', 'kirildim']) => 'sad',
            Str::contains($text, ['sinir', 'kizgin', 'sacma', 'of ya']) => 'angry',
            Str::contains($text, ['tatlisin', 'guzelsin', 'yakisikli', 'ozledim']) => 'flirty',
            Str::contains($text, ['haha', 'jsjs', 'guldum', 'komik']) => 'playful',
            Str::contains($text, ['heyecan', 'merak', '?']) => 'curious',
            default => 'neutral',
        };
    }

    private function energy(string $text): string
    {
        $length = mb_strlen($text);

        return match (true) {
            $length === 0 => 'low',
            $length <= 12 => 'low',
            Str::contains($text, ['!!!', '??', 'hadi', 'hemen']) => 'high',
            $length >= 120 => 'high',
            default => 'medium',
        };
    }

    private function riskLevel(string $text): string
    {
        if (Str::contains($text, ['iban', 'papara', 'havale', 'para gonder'])) {
            return 'high';
        }

        if (Str::contains($text, ['whatsapp', 'telegram', 'numara', 'telefon'])) {
            return 'high';
        }

        if (Str::contains($text, ['seks', 'ciplak', 'hot', 'fantezi'])) {
            return 'medium';
        }

        return 'low';
    }

    private function expectation(string $text, string $intent, string $emotion): string
    {
        if ($intent === 'question') {
            return 'direct_answer';
        }

        if ($emotion === 'sad') {
            return 'comfort';
        }

        if ($emotion === 'flirty') {
            return 'playful_reciprocity';
        }

        if ($intent === 'greeting') {
            return 'warm_opening';
        }

        return 'keep_flow';
    }

    private function topics(string $text): array
    {
        $topics = [];
        $keywordMap = [
            'muzik' => ['muzik', 'sarki', 'konser'],
            'sehir' => ['istanbul', 'ankara', 'izmir', 'sehir', 'memleket'],
            'iliski' => ['iliski', 'ask', 'sevgili', 'flort'],
            'is' => ['is', 'ofis', 'calisiyorum', 'meslek'],
            'okul' => ['universite', 'okul', 'bolum', 'ders'],
        ];

        foreach ($keywordMap as $topic => $keywords) {
            if (Str::contains($text, $keywords)) {
                $topics[] = $topic;
            }
        }

        if ($topics === []) {
            $tokens = preg_split('/\s+/', $text) ?: [];
            $topics = collect($tokens)
                ->map(fn (string $token) => preg_replace('/[^a-z0-9cigiosu]/', '', $token) ?? '')
                ->filter(fn (string $token) => mb_strlen($token) >= 5)
                ->take(3)
                ->values()
                ->all();
        }

        return array_values(array_unique($topics));
    }
}
