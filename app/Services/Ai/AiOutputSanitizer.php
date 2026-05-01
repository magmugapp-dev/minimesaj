<?php

namespace App\Services\Ai;

class AiOutputSanitizer
{
    public static function sanitize(string $raw): SanitizeResult
    {
        $strippedBlocks = [];
        $clean = preg_replace_callback(
            '/<([a-zA-Z_]+)>[\s\S]*?<\/\1>/',
            function (array $match) use (&$strippedBlocks): string {
                $strippedBlocks[] = [
                    'tag' => $match[1],
                    'content' => $match[0],
                ];

                return '';
            },
            $raw,
        ) ?? $raw;

        [$clean, $detectedTags] = self::extractSystemTags($clean);
        $clean = trim(preg_replace("/[ \t]+\n/", "\n", $clean) ?? $clean);

        return new SanitizeResult(
            clean: $clean,
            strippedBlocks: $strippedBlocks,
            detectedTags: $detectedTags,
        );
    }

    private static function extractSystemTags(string $value): array
    {
        $detected = [];
        $clean = preg_replace_callback(
            '/\[(CRISIS_DETECTED|BLOCK_USER:[a-z_]+|GHOST_USER:[a-z_]+)\]/i',
            function (array $match) use (&$detected): string {
                $detected[] = strtoupper($match[1]);

                return '';
            },
            $value,
        ) ?? $value;

        $truncationPatterns = [
            '/\[CRISI\b[^\]]*$/i' => 'CRISIS_DETECTED',
            '/\[BLOCK_USER\b[^\]]*$/i' => 'BLOCK_USER:absolute_violation',
            '/\[GHOST_USER\b[^\]]*$/i' => 'GHOST_USER:silent',
        ];

        foreach ($truncationPatterns as $pattern => $tag) {
            if (preg_match($pattern, $clean) === 1) {
                $detected[] = strtoupper($tag);
                $clean = preg_replace($pattern, '', $clean) ?? $clean;
            }
        }

        return [$clean, array_values(array_unique($detected))];
    }
}

class SanitizeResult
{
    public function __construct(
        public string $clean,
        public array $strippedBlocks = [],
        public array $detectedTags = [],
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->clean) === '';
    }
}
