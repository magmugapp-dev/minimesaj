<?php

namespace App\Services;

use App\Models\AppFaqItem;
use App\Models\AppLanguage;
use App\Models\AppLegalDocument;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AppContentService
{
    public const FALLBACK_LANGUAGE = 'en';

    public function payload(?string $requestedLanguage): array
    {
        $activeLanguages = $this->activeLanguages();
        $defaultLanguage = $this->defaultLanguage($activeLanguages);
        $selectedLanguage = $this->selectedLanguage($requestedLanguage, $activeLanguages, $defaultLanguage);

        $payload = [
            'languages' => $activeLanguages->map(fn(AppLanguage $language): array => $this->languagePayload($language))->values()->all(),
            'defaultLanguage' => $defaultLanguage?->code ?? self::FALLBACK_LANGUAGE,
            'selectedLanguage' => $selectedLanguage?->code ?? $defaultLanguage?->code ?? self::FALLBACK_LANGUAGE,
            'translations' => $this->translationsPayload($selectedLanguage, $activeLanguages),
            'legalTexts' => $this->legalTextsPayload($selectedLanguage, $activeLanguages),
            'faqs' => $this->faqPayload($selectedLanguage, $activeLanguages),
        ];

        $payload['updatedAt'] = $this->contentUpdatedAt();
        $payload['version'] = sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return $payload;
    }

    public function activeLanguages(): Collection
    {
        return AppLanguage::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function defaultLanguage(?Collection $activeLanguages = null): ?AppLanguage
    {
        $activeLanguages ??= $this->activeLanguages();

        return $activeLanguages->firstWhere('is_default', true)
            ?? $activeLanguages->firstWhere('code', self::FALLBACK_LANGUAGE)
            ?? $activeLanguages->first();
    }

    private function selectedLanguage(
        ?string $requestedLanguage,
        Collection $activeLanguages,
        ?AppLanguage $defaultLanguage,
    ): ?AppLanguage {
        $code = $this->normalizeCode($requestedLanguage);

        if ($code !== null) {
            $language = $activeLanguages->firstWhere('code', $code);
            if ($language instanceof AppLanguage) {
                return $language;
            }
        }

        return $activeLanguages->firstWhere('code', self::FALLBACK_LANGUAGE)
            ?? $defaultLanguage
            ?? $activeLanguages->first();
    }

    private function translationsPayload(?AppLanguage $selectedLanguage, Collection $activeLanguages): array
    {
        $keys = AppTranslationKey::query()
            ->where('is_active', true)
            ->with(['translations' => fn($query) => $query->where('is_active', true)->with('language')])
            ->orderBy('key')
            ->get();

        $payload = [];

        foreach ($keys as $translationKey) {
            $translations = $translationKey->translations
                ->filter(fn($translation): bool => $translation->language?->is_active === true);

            $value = $this->translationValueFor($translations, $selectedLanguage?->code)
                ?? $this->translationValueFor($translations, self::FALLBACK_LANGUAGE)
                ?? $translations->first(fn($translation): bool => trim((string) $translation->value) !== '')?->value
                ?? $translationKey->default_value
                ?? $translationKey->key;

            $payload[$translationKey->key] = (string) $value;
        }

        return $payload;
    }

    private function legalTextsPayload(?AppLanguage $selectedLanguage, Collection $activeLanguages): array
    {
        $documents = AppLegalDocument::query()
            ->where('is_active', true)
            ->with('language')
            ->get()
            ->filter(fn(AppLegalDocument $document): bool => $document->language?->is_active === true)
            ->groupBy('type');

        $payload = [];

        foreach (AppLegalDocument::TYPES as $type => $fallbackTitle) {
            $group = $documents->get($type, collect());
            $document = $this->documentFor($group, $selectedLanguage?->code)
                ?? $this->documentFor($group, self::FALLBACK_LANGUAGE)
                ?? $group->first();

            $payload[$type] = [
                'type' => $type,
                'title' => $document?->title ?: $fallbackTitle,
                'content' => (string) ($document?->content ?? ''),
                'languageCode' => $document?->language?->code,
                'updatedAt' => $document?->updated_at?->toISOString(),
            ];
        }

        return $payload;
    }

    private function faqPayload(?AppLanguage $selectedLanguage, Collection $activeLanguages): array
    {
        $language = $this->faqLanguage($selectedLanguage?->code)
            ?? $this->faqLanguage(self::FALLBACK_LANGUAGE)
            ?? AppFaqItem::query()
                ->where('is_active', true)
                ->with('language')
                ->get()
                ->first(fn(AppFaqItem $item): bool => $item->language?->is_active === true)
                ?->language;

        if (! $language instanceof AppLanguage) {
            return [];
        }

        return AppFaqItem::query()
            ->where('app_language_id', $language->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn(AppFaqItem $item): array => [
                'id' => $item->id,
                'question' => $item->question,
                'answer' => (string) $item->answer,
                'category' => $item->category,
                'screen' => $item->screen,
                'sortOrder' => $item->sort_order,
                'languageCode' => $language->code,
                'updatedAt' => $item->updated_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function translationValueFor(Collection $translations, ?string $languageCode): ?string
    {
        if ($languageCode === null) {
            return null;
        }

        $translation = $translations->first(
            fn($item): bool => $item->language?->code === $languageCode && trim((string) $item->value) !== ''
        );

        return $translation?->value;
    }

    private function documentFor(Collection $documents, ?string $languageCode): ?AppLegalDocument
    {
        if ($languageCode === null) {
            return null;
        }

        return $documents->first(
            fn(AppLegalDocument $document): bool => $document->language?->code === $languageCode
                && (trim((string) $document->title) !== '' || trim((string) $document->content) !== '')
        );
    }

    private function faqLanguage(?string $languageCode): ?AppLanguage
    {
        if ($languageCode === null) {
            return null;
        }

        return AppLanguage::query()
            ->where('code', $languageCode)
            ->where('is_active', true)
            ->whereHas('faqItems', fn($query) => $query->where('is_active', true))
            ->first();
    }

    private function languagePayload(AppLanguage $language): array
    {
        return [
            'code' => $language->code,
            'name' => $language->name,
            'nativeName' => $language->native_name,
            'isActive' => $language->is_active,
            'isDefault' => $language->is_default,
            'sortOrder' => $language->sort_order,
            'updatedAt' => $language->updated_at?->toISOString(),
        ];
    }

    private function contentUpdatedAt(): ?string
    {
        $timestamps = collect([
            AppLanguage::query()->max('updated_at'),
            AppTranslationKey::query()->max('updated_at'),
            AppTranslation::query()->max('updated_at'),
            AppLegalDocument::query()->max('updated_at'),
            AppFaqItem::query()->max('updated_at'),
        ])->filter();

        $latest = $timestamps->max();
        if ($latest === null) {
            return null;
        }

        return $latest instanceof CarbonInterface
            ? $latest->toISOString()
            : \Carbon\Carbon::parse($latest)->toISOString();
    }

    private function normalizeCode(?string $code): ?string
    {
        $normalized = strtolower(trim((string) $code));

        return $normalized === '' ? null : substr($normalized, 0, 12);
    }
}
