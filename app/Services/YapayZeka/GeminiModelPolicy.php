<?php

namespace App\Services\YapayZeka;

use Illuminate\Support\Str;

class GeminiModelPolicy
{
    public const AUTO_QUALITY = 'gemini-3.1-auto-quality';
    public const PRIMARY_MODEL = 'gemini-3.1-pro-preview';
    public const SECONDARY_MODEL = 'gemini-3-flash-preview';
    public const TERTIARY_MODEL = 'gemini-3.1-flash-lite-preview';

    public static function normalizeConfiguredModel(?string $modelAdi): string
    {
        $trimmed = trim((string) $modelAdi);

        if ($trimmed === '') {
            return self::AUTO_QUALITY;
        }

        $lower = Str::lower($trimmed);

        if ($lower === self::AUTO_QUALITY) {
            return self::AUTO_QUALITY;
        }

        if (!Str::startsWith($lower, 'gemini')) {
            return self::AUTO_QUALITY;
        }

        if (Str::startsWith($lower, 'gemini-2.5')) {
            return self::AUTO_QUALITY;
        }

        return $trimmed;
    }

    public static function concreteModelChain(?string $modelAdi): array
    {
        $normalized = self::normalizeConfiguredModel($modelAdi);

        if ($normalized !== self::AUTO_QUALITY) {
            return [$normalized];
        }

        return [
            self::PRIMARY_MODEL,
            self::SECONDARY_MODEL,
            self::TERTIARY_MODEL,
        ];
    }

    public static function isAutoQuality(?string $modelAdi): bool
    {
        return self::normalizeConfiguredModel($modelAdi) === self::AUTO_QUALITY;
    }

    public static function perModelAttemptBudgets(?string $modelAdi): array
    {
        if (self::isAutoQuality($modelAdi)) {
            return [2, 2, 1];
        }

        return [5];
    }

    public static function defaultConcreteModel(): string
    {
        return self::PRIMARY_MODEL;
    }
}
