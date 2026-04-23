<?php

namespace App\Services\YapayZeka\V2\Data;

final class AiGenerationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $memoryCandidates
     */
    public function __construct(
        public readonly string $replyText,
        public readonly array $memoryCandidates = [],
        public readonly ?string $rawResponse = null,
        public readonly ?string $model = null,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly string $promptSummary = '',
    ) {}

    public function withReply(string $replyText): self
    {
        return new self(
            $replyText,
            $this->memoryCandidates,
            $this->rawResponse,
            $this->model,
            $this->inputTokens,
            $this->outputTokens,
            $this->promptSummary,
        );
    }

    public function toArray(): array
    {
        return [
            'reply_text' => $this->replyText,
            'memory_candidates' => $this->memoryCandidates,
            'raw_response' => $this->rawResponse,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'prompt_summary' => $this->promptSummary,
        ];
    }
}
