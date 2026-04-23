<?php

namespace App\Services\YapayZeka\V2\Data;

final class AiResponsePlan
{
    public function __construct(
        public readonly string $aim,
        public readonly string $tone,
        public readonly int $minChars,
        public readonly int $maxChars,
        public readonly bool $askQuestion,
        public readonly int $emojiLevel,
        public readonly int $flirtLevel,
        public readonly string $styleHint,
    ) {}

    public function toArray(): array
    {
        return [
            'aim' => $this->aim,
            'tone' => $this->tone,
            'min_chars' => $this->minChars,
            'max_chars' => $this->maxChars,
            'ask_question' => $this->askQuestion,
            'emoji_level' => $this->emojiLevel,
            'flirt_level' => $this->flirtLevel,
            'style_hint' => $this->styleHint,
        ];
    }
}
