<?php

namespace App\Services\YapayZeka\V2\Data;

final class AiInterpretation
{
    /**
     * @param  array<int, string>  $topics
     */
    public function __construct(
        public readonly string $intent,
        public readonly string $emotion,
        public readonly string $energy,
        public readonly string $riskLevel,
        public readonly string $expectation,
        public readonly array $topics = [],
        public readonly string $summary = '',
    ) {}

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'emotion' => $this->emotion,
            'energy' => $this->energy,
            'risk_level' => $this->riskLevel,
            'expectation' => $this->expectation,
            'topics' => $this->topics,
            'summary' => $this->summary,
        ];
    }
}
