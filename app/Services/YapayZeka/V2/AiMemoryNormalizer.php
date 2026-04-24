<?php

namespace App\Services\YapayZeka\V2;

use App\Support\Language;
use Illuminate\Support\Str;

class AiMemoryNormalizer
{
    private const KEY_ALIASES = [
        'nickname' => 'identity.nickname',
        'nick_name' => 'identity.nickname',
        'display_name' => 'identity.nickname',
        'age' => 'age.current',
        'current_age' => 'age.current',
        'birth_year' => 'birth.year',
        'city' => 'location.city',
        'current_city' => 'location.city',
        'home_city' => 'location.city',
        'country' => 'location.country',
        'current_country' => 'location.country',
        'region' => 'location.region',
        'current_region' => 'location.region',
        'origin' => 'culture.origin',
        'cultural_origin' => 'culture.origin',
        'ethnicity' => 'culture.origin',
        'nationality' => 'identity.nationality',
        'language' => 'language.primary',
        'native_language' => 'language.primary',
        'primary_language' => 'language.primary',
        'spoken_language' => 'language.spoken',
        'job' => 'job.current',
        'current_job' => 'job.current',
        'profession' => 'job.current',
        'work' => 'job.current',
        'sector' => 'job.sector',
        'industry' => 'job.sector',
        'education' => 'education.level',
        'education_level' => 'education.level',
        'school' => 'education.school',
        'university' => 'education.school',
        'department' => 'education.department',
        'major' => 'education.department',
        'relationship' => 'relationship.status',
        'relationship_status' => 'relationship.status',
        'marital_status' => 'relationship.status',
        'family' => 'family.note',
        'family_note' => 'family.note',
        'siblings' => 'family.siblings_count',
        'pet' => 'pet.current',
        'pets' => 'pet.current',
        'hobby' => 'hobby.primary',
        'hobbies' => 'hobby.primary',
        'routine' => 'routine.daily',
        'goal' => 'goal.current',
        'goals' => 'goal.current',
        'boundary' => 'boundary.current',
        'boundaries' => 'boundary.current',
        'life_event' => 'life_event.recent',
        'important_life_event' => 'life_event.recent',
    ];

    public function key(?string $key): ?string
    {
        $normalized = Str::of((string) $key)
            ->trim()
            ->lower()
            ->ascii()
            ->toString();

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(['.', '-', ' '], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            return null;
        }

        $normalized = $this->canonicalKey($normalized);

        return $normalized === '' ? null : $normalized;
    }

    public function value(mixed $value): ?string
    {
        return $this->valueForKey(null, $value);
    }

    public function valueForKey(?string $key, mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value)));
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $key = $this->key($key);
        $semantic = $this->semanticValue($key, $raw);
        if ($semantic !== null) {
            return $semantic;
        }

        $normalized = Str::of($raw)
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

    private function canonicalKey(string $normalized): string
    {
        if (isset(self::KEY_ALIASES[$normalized])) {
            return str_replace('.', '_', self::KEY_ALIASES[$normalized]);
        }

        if (str_starts_with($normalized, 'location_')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'education_')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'job_')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'language_')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'preference_likes_')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'preference_dislikes_')) {
            return $normalized;
        }

        return $normalized;
    }

    private function semanticValue(?string $key, string $value): ?string
    {
        $normalized = Str::of($value)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->replaceMatches('/[^\pL\pN\s\-\.]/u', '')
            ->trim()
            ->toString();

        if ($normalized === '') {
            return null;
        }

        return match ($key) {
            'relationship_status' => $this->relationshipStatus($normalized),
            'language_primary', 'language_spoken' => $this->languageValue($normalized) ?? $normalized,
            'age_current', 'birth_year', 'family_siblings_count' => preg_match('/\d{1,4}/', $normalized, $matches) === 1
                ? $matches[0]
                : $normalized,
            default => $normalized,
        };
    }

    private function relationshipStatus(string $value): string
    {
        return match (true) {
            Str::contains($value, ['bekar', 'single']) => 'single',
            Str::contains($value, ['evli', 'married']) => 'married',
            Str::contains($value, ['iliski', 'relationship', 'sevgili']) => 'in_relationship',
            Str::contains($value, ['bosanmis', 'divorced']) => 'divorced',
            Str::contains($value, ['complicated', 'karisik']) => 'complicated',
            default => $value,
        };
    }

    private function languageValue(string $value): ?string
    {
        return Language::normalizeCode($value)
            ?: Language::codeFromName($value)
            ?: null;
    }
}
