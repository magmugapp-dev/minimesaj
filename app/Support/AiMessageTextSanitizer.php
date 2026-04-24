<?php

namespace App\Support;

class AiMessageTextSanitizer
{
    public static function sanitize(?string $text): ?string
    {
        $normalized = self::normalize($text);
        if ($normalized === null) {
            return null;
        }

        $unwrapped = self::unwrapEnvelope($normalized);
        if ($unwrapped === null) {
            return self::isEnvelopeCandidate($normalized) ? null : $normalized;
        }

        $unwrapped = self::normalize($unwrapped);

        return $unwrapped === null ? $normalized : self::sanitize($unwrapped);
    }

    private static function normalize(?string $text): ?string
    {
        $normalized = trim((string) $text);

        return $normalized === '' ? null : $normalized;
    }

    private static function unwrapEnvelope(string $text): ?string
    {
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text, $matches) === 1) {
            return self::unwrapEnvelope($matches[1]) ?? $matches[1];
        }

        if (!self::looksLikeJson($text)) {
            return null;
        }

        try {
            $decoded = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return self::extractEnvelopeText($decoded);
    }

    private static function looksLikeJson(string $text): bool
    {
        return str_starts_with($text, '{') || str_starts_with($text, '[');
    }

    private static function isEnvelopeCandidate(string $text): bool
    {
        return preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text) === 1 || self::looksLikeJson($text);
    }

    private static function extractEnvelopeText(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach (['reply', 'cevap', 'text', 'message', 'content', 'mesaj'] as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }

            $candidate = self::extractEnvelopeText($value[$key]);
            if ($candidate !== null && trim($candidate) !== '') {
                return $candidate;
            }
        }

        if (isset($value['parts']) && is_array($value['parts'])) {
            foreach ($value['parts'] as $part) {
                $candidate = self::extractEnvelopeText($part);
                if ($candidate !== null && trim($candidate) !== '') {
                    return $candidate;
                }
            }
        }

        if (isset($value['candidates']) && is_array($value['candidates'])) {
            foreach ($value['candidates'] as $candidateValue) {
                $candidate = self::extractEnvelopeText($candidateValue);
                if ($candidate !== null && trim($candidate) !== '') {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
