<?php

namespace App\Services\YapayZeka\V2;

use Illuminate\Support\Str;

class AiJsonResponseParser
{
    public function parseReply(string $raw): array
    {
        $decoded = $this->decode($raw);

        if (is_array($decoded) && array_key_exists('reply', $decoded)) {
            return [
                'reply' => trim((string) ($decoded['reply'] ?? '')),
                'memory' => is_array($decoded['memory'] ?? null) ? $decoded['memory'] : [],
                'payload' => $decoded,
            ];
        }

        return [
            'reply' => trim($this->rescueJsonStringField($raw, 'reply') ?: $raw),
            'memory' => [],
            'payload' => is_array($decoded) ? $decoded : [],
        ];
    }

    public function decode(string $raw): mixed
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    private function rescueJsonStringField(string $raw, string $field): ?string
    {
        $pattern = '/"' . preg_quote($field, '/') . '"\s*:\s*"((?:[^"\\\\]|\\\\.)*)/u';
        if (!preg_match($pattern, $raw, $matches)) {
            return null;
        }

        $value = $matches[1] ?? '';
        $decoded = json_decode('"' . $value . '"', true);

        return is_string($decoded) ? trim($decoded) : Str::of($value)->replace('\\"', '"')->toString();
    }
}
