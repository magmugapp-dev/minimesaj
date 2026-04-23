<?php

namespace App\Services\YapayZeka\V2;

use Illuminate\Support\Str;

class AiMemoryNormalizer
{
    public function key(?string $key): ?string
    {
        $normalized = Str::of((string) $key)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return $normalized === '' ? null : $normalized;
    }

    public function value(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }

        $normalized = Str::of((string) $value)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->replaceMatches('/[^\pL\pN\s\-\.]/u', '')
            ->trim()
            ->toString();

        return $normalized === '' ? null : $normalized;
    }

    public function displayValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
