<?php

namespace App\Support;

use Illuminate\Support\Str;

class Language
{
    private const NAMES = [
        'tr' => 'Turkish',
        'en' => 'English',
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'nl' => 'Dutch',
        'ar' => 'Arabic',
        'ru' => 'Russian',
        'uk' => 'Ukrainian',
        'pl' => 'Polish',
        'az' => 'Azerbaijani',
        'fa' => 'Persian',
        'hi' => 'Hindi',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'zh' => 'Chinese',
    ];

    public static function normalizeCode(?string $code): ?string
    {
        $normalized = Str::lower(trim((string) $code));
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace('_', '-', $normalized);
        $primary = explode('-', $normalized)[0] ?? $normalized;

        return preg_match('/^[a-z]{2,3}$/', $primary) ? $primary : null;
    }

    public static function name(?string $code, ?string $fallback = null): ?string
    {
        $normalized = self::normalizeCode($code);
        if ($normalized === null) {
            return $fallback;
        }

        return self::NAMES[$normalized] ?? strtoupper($normalized);
    }

    public static function codeFromName(?string $name): ?string
    {
        $normalized = Str::of((string) $name)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z]+/', ' ')
            ->squish()
            ->toString();

        if ($normalized === '') {
            return null;
        }

        $aliases = [
            'turkce' => 'tr',
            'turkish' => 'tr',
            'english' => 'en',
            'ingilizce' => 'en',
            'german' => 'de',
            'almanca' => 'de',
            'french' => 'fr',
            'fransizca' => 'fr',
            'spanish' => 'es',
            'ispanyolca' => 'es',
            'italian' => 'it',
            'italyanca' => 'it',
            'arabic' => 'ar',
            'arapca' => 'ar',
            'russian' => 'ru',
            'rusca' => 'ru',
        ];

        return $aliases[$normalized] ?? null;
    }
}
