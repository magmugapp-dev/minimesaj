<?php

namespace App\Services\YapayZeka;

use Illuminate\Support\Str;

class GeminiModelPolicy
{
    public const AUTO_QUALITY = 'gemini-3.1-auto-quality';
    public const PRIMARY_MODEL = 'gemini-3.1-pro-preview';
    public const SECONDARY_MODEL = 'gemini-3-flash-preview';
    public const TERTIARY_MODEL = 'gemini-3.1-flash-lite-preview';
    public const DEFAULT_THINKING_BUDGET = 1024;

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
            self::SECONDARY_MODEL,
            self::TERTIARY_MODEL,
            self::PRIMARY_MODEL,
        ];
    }

    public static function firstConcreteModel(?string $modelAdi = null): string
    {
        $chain = self::concreteModelChain($modelAdi);

        return $chain[0] ?? self::PRIMARY_MODEL;
    }

    public static function isPolicyToken(?string $modelAdi): bool
    {
        return Str::lower(trim((string) $modelAdi)) === self::AUTO_QUALITY;
    }

    public static function isAutoQuality(?string $modelAdi): bool
    {
        return self::normalizeConfiguredModel($modelAdi) === self::AUTO_QUALITY;
    }

    public static function perModelAttemptBudgets(?string $modelAdi): array
    {
        if (self::isAutoQuality($modelAdi)) {
            return [1, 1, 1];
        }

        return [1];
    }

    public static function thinkingBudgetForModel(string $modelAdi, mixed $requestedBudget = null): ?int
    {
        $requestedBudget = is_numeric($requestedBudget) ? (int) $requestedBudget : null;

        if ($requestedBudget !== null && $requestedBudget > 0) {
            return $requestedBudget;
        }

        if (self::requiresThinkingBudget($modelAdi)) {
            return self::DEFAULT_THINKING_BUDGET;
        }

        return null;
    }

    public static function requiresThinkingBudget(string $modelAdi): bool
    {
        return Str::lower(trim($modelAdi)) === self::PRIMARY_MODEL;
    }

    public static function defaultConcreteModel(): string
    {
        return self::firstConcreteModel(self::AUTO_QUALITY);
    }
}
