<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppFaqItem;
use App\Models\AppLanguage;
use App\Models\AppLegalDocument;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DilMetinController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'keys');
        $keyArchiveMode = $request->query('archive') === 'archived';
        $languageArchiveMode = $request->query('language_archive') === 'archived';
        $legalArchiveMode = $request->query('legal_archive') === 'archived';
        $faqArchiveMode = $request->query('faq_archive') === 'archived';

        $languages = AppLanguage::query()
            ->when($languageArchiveMode, fn($query) => $query->onlyTrashed())
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $activeLanguages = AppLanguage::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedLanguageId = (int) ($request->query('language_id') ?: ($activeLanguages->firstWhere('is_default', true)?->id ?? $activeLanguages->first()?->id));

        $folderTree = $this->folderTree($keyArchiveMode, $request, $selectedLanguageId);

        $requestedSelectedKey = null;
        if ($request->filled('selected_key_id')) {
            $requestedSelectedKey = AppTranslationKey::withTrashed()
                ->with(['translations.language'])
                ->find($request->query('selected_key_id'));
            if ($requestedSelectedKey?->trashed() !== $keyArchiveMode) {
                $requestedSelectedKey = null;
            }
        }

        $selectedCategory = $this->folderLabelFromFilter($request->query('category'));
        if (! $selectedCategory && $requestedSelectedKey) {
            $selectedCategory = $this->folderLabelFor($requestedSelectedKey->category);
        }
        $selectedCategory ??= array_key_first($folderTree);

        $screenFolders = $selectedCategory ? ($folderTree[$selectedCategory]['screens'] ?? []) : [];
        $selectedScreen = $this->folderLabelFromFilter($request->query('screen'));
        if (! $selectedScreen && $requestedSelectedKey && $selectedCategory === $this->folderLabelFor($requestedSelectedKey->category)) {
            $selectedScreen = $this->folderLabelFor($requestedSelectedKey->screen);
        }
        $selectedScreen ??= array_key_first($screenFolders);

        $translationKeyQuery = AppTranslationKey::query()
            ->when($keyArchiveMode, fn($query) => $query->onlyTrashed())
            ->with(['translations.language']);

        $this->applyKeyFilters($translationKeyQuery, $request, $selectedLanguageId);

        $translationKeyQuery
            ->orderBy('category')
            ->orderBy('screen')
            ->orderBy('key');

        $translationKeys = $translationKeyQuery
            ->paginate(30)
            ->withQueryString();

        $columnKeyQuery = AppTranslationKey::query()
            ->when($keyArchiveMode, fn($query) => $query->onlyTrashed())
            ->with(['translations.language']);

        $this->applyKeyFilters($columnKeyQuery, $request, $selectedLanguageId, false);
        if ($selectedCategory) {
            $this->applyNullableTextFilter($columnKeyQuery, 'category', $this->folderFilterValue($selectedCategory));
        }
        if ($selectedScreen) {
            $this->applyNullableTextFilter($columnKeyQuery, 'screen', $this->folderFilterValue($selectedScreen));
        }

        $columnKeys = $columnKeyQuery
            ->orderBy('key')
            ->get();

        $categories = AppTranslationKey::query()
            ->when($keyArchiveMode, fn($query) => $query->onlyTrashed())
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $screens = AppTranslationKey::query()
            ->when($keyArchiveMode, fn($query) => $query->onlyTrashed())
            ->whereNotNull('screen')
            ->distinct()
            ->orderBy('screen')
            ->pluck('screen');

        $selectedKey = $requestedSelectedKey;
        if ($selectedKey && ! $columnKeys->contains('id', $selectedKey->id)) {
            $selectedKey = null;
        }
        $selectedKey ??= $columnKeys->first();

        $legalDocuments = AppLegalDocument::query()
            ->with('language')
            ->when($legalArchiveMode, fn($query) => $query->onlyTrashed())
            ->orderBy('type')
            ->get()
            ->groupBy(fn(AppLegalDocument $document): string => $document->type . ':' . $document->app_language_id);

        $faqItems = AppFaqItem::query()
            ->with('language')
            ->when($faqArchiveMode, fn($query) => $query->onlyTrashed())
            ->orderBy('app_language_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.dil-metin.index', [
            'tab' => $tab,
            'languages' => $languages,
            'activeLanguages' => $activeLanguages,
            'selectedLanguageId' => $selectedLanguageId,
            'translationKeys' => $translationKeys,
            'columnKeys' => $columnKeys,
            'selectedKey' => $selectedKey,
            'folderTree' => $folderTree,
            'screenFolders' => $screenFolders,
            'selectedCategory' => $selectedCategory,
            'selectedScreen' => $selectedScreen,
            'categories' => $categories,
            'screens' => $screens,
            'legalDocuments' => $legalDocuments,
            'legalTypes' => AppLegalDocument::TYPES,
            'faqItems' => $faqItems,
            'keyArchiveMode' => $keyArchiveMode,
            'languageArchiveMode' => $languageArchiveMode,
            'legalArchiveMode' => $legalArchiveMode,
            'faqArchiveMode' => $faqArchiveMode,
        ]);
    }

    public function storeLanguage(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/i', 'unique:app_languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $validated['code'] = strtolower($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_default'] = $request->boolean('is_default');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($validated['is_default']) {
            AppLanguage::query()->update(['is_default' => false]);
            $validated['is_active'] = true;
        }

        AppLanguage::query()->create($validated);

        return back()->with('basari', 'Dil eklendi.');
    }

    public function updateLanguage(Request $request, AppLanguage $language)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/i', Rule::unique('app_languages', 'code')->ignore($language->id)],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $validated['code'] = strtolower($validated['code']);
        $validated['is_active'] = $language->is_default ? true : $request->boolean('is_active');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        $language->update($validated);

        return back()->with('basari', 'Dil guncellendi.');
    }

    public function makeDefaultLanguage(AppLanguage $language)
    {
        AppLanguage::query()->update(['is_default' => false]);
        $language->update(['is_default' => true, 'is_active' => true]);

        return back()->with('basari', 'Varsayilan dil guncellendi.');
    }

    public function destroyLanguage(AppLanguage $language)
    {
        if ($language->is_default) {
            return back()->with('hata', 'Varsayilan dil arsivlenemez.');
        }

        $language->delete();

        return back()->with('basari', 'Dil arsivlendi.');
    }

    public function restoreLanguage(int $language)
    {
        $language = AppLanguage::withTrashed()->findOrFail($language);
        $language->restore();

        return back()->with('basari', 'Dil arsivden geri alindi.');
    }

    public function forceDestroyLanguage(int $language)
    {
        $language = AppLanguage::withTrashed()->findOrFail($language);
        if ($language->is_default) {
            return back()->with('hata', 'Varsayilan dil kalici silinemez.');
        }

        $language->forceDelete();

        return back()->with('basari', 'Dil kalici silindi.');
    }

    public function storeTranslationKey(Request $request)
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255', 'unique:app_translation_keys,key'],
            'default_value' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'screen' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $translationKey = AppTranslationKey::query()->create($validated);
        $fallback = (string) ($validated['default_value'] ?? '');

        AppLanguage::query()
            ->where('is_active', true)
            ->get()
            ->each(function (AppLanguage $language) use ($translationKey, $fallback): void {
                AppTranslation::query()->firstOrCreate(
                    [
                        'app_translation_key_id' => $translationKey->id,
                        'app_language_id' => $language->id,
                    ],
                    [
                        'value' => $fallback,
                        'is_active' => true,
                    ]
                );
            });

        return redirect()
            ->route('admin.dil-metin.index', ['tab' => 'keys', 'selected_key_id' => $translationKey->id])
            ->with('basari', 'Translation key eklendi.');
    }

    public function updateTranslationKey(Request $request, AppTranslationKey $translationKey)
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255', Rule::unique('app_translation_keys', 'key')->ignore($translationKey->id)],
            'default_value' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'screen' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $translationKey->update($validated);

        return back()->with('basari', 'Translation key guncellendi.');
    }

    public function destroyTranslationKey(AppTranslationKey $translationKey)
    {
        $translationKey->delete();

        return back()->with('basari', 'Translation key arsivlendi.');
    }

    public function bulkArchiveTranslationKeys(Request $request)
    {
        $validated = $request->validate([
            'key_ids' => ['required', 'array', 'min:1'],
            'key_ids.*' => ['integer', 'exists:app_translation_keys,id'],
        ]);

        AppTranslationKey::query()
            ->whereIn('id', $validated['key_ids'])
            ->delete();

        return back()->with('basari', 'Secili keyler arsivlendi.');
    }

    public function restoreTranslationKey(int $translationKey)
    {
        $translationKey = AppTranslationKey::withTrashed()->findOrFail($translationKey);
        $translationKey->restore();

        return back()->with('basari', 'Translation key arsivden geri alindi.');
    }

    public function forceDestroyTranslationKey(int $translationKey)
    {
        $translationKey = AppTranslationKey::withTrashed()->findOrFail($translationKey);
        $translationKey->forceDelete();

        return back()->with('basari', 'Translation key kalici silindi.');
    }

    public function updateTranslation(Request $request, AppTranslationKey $translationKey)
    {
        $validated = $request->validate([
            'app_language_id' => ['required', 'exists:app_languages,id'],
            'value' => ['nullable', 'string'],
        ]);

        AppTranslation::query()->updateOrCreate(
            [
                'app_translation_key_id' => $translationKey->id,
                'app_language_id' => $validated['app_language_id'],
            ],
            [
                'value' => $validated['value'] ?? '',
                'is_active' => $request->boolean('is_active', true),
            ]
        );

        return back()->with('basari', 'Ceviri guncellendi.');
    }

    public function updateLegalDocument(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(AppLegalDocument::TYPES))],
            'app_language_id' => ['required', 'exists:app_languages,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ]);

        $document = AppLegalDocument::withTrashed()->updateOrCreate(
            [
                'type' => $validated['type'],
                'app_language_id' => $validated['app_language_id'],
            ],
            [
                'title' => $validated['title'],
                'content' => $validated['content'] ?? '',
                'is_active' => $request->boolean('is_active', true),
            ]
        );
        if ($document->trashed()) {
            $document->restore();
        }

        return back()->with('basari', 'Yasal metin guncellendi.');
    }

    public function destroyLegalDocument(AppLegalDocument $legalDocument)
    {
        $legalDocument->delete();

        return back()->with('basari', 'Yasal metin arsivlendi.');
    }

    public function restoreLegalDocument(int $legalDocument)
    {
        $legalDocument = AppLegalDocument::withTrashed()->findOrFail($legalDocument);
        $legalDocument->restore();

        return back()->with('basari', 'Yasal metin arsivden geri alindi.');
    }

    public function forceDestroyLegalDocument(int $legalDocument)
    {
        $legalDocument = AppLegalDocument::withTrashed()->findOrFail($legalDocument);
        $legalDocument->forceDelete();

        return back()->with('basari', 'Yasal metin kalici silindi.');
    }

    public function storeFaq(Request $request)
    {
        $validated = $this->faqValidation($request);
        $validated['is_active'] = $request->boolean('is_active', true);

        AppFaqItem::query()->create($validated);

        return back()->with('basari', 'FAQ eklendi.');
    }

    public function updateFaq(Request $request, AppFaqItem $faqItem)
    {
        $validated = $this->faqValidation($request);
        $validated['is_active'] = $request->boolean('is_active');

        $faqItem->update($validated);

        return back()->with('basari', 'FAQ guncellendi.');
    }

    public function destroyFaq(AppFaqItem $faqItem)
    {
        $faqItem->delete();

        return back()->with('basari', 'FAQ arsivlendi.');
    }

    public function restoreFaq(int $faqItem)
    {
        $faqItem = AppFaqItem::withTrashed()->findOrFail($faqItem);
        $faqItem->restore();

        return back()->with('basari', 'FAQ arsivden geri alindi.');
    }

    public function forceDestroyFaq(int $faqItem)
    {
        $faqItem = AppFaqItem::withTrashed()->findOrFail($faqItem);
        $faqItem->forceDelete();

        return back()->with('basari', 'FAQ kalici silindi.');
    }

    public function apiMeta(): JsonResponse
    {
        return $this->jsonSuccess([
            'languages' => AppLanguage::query()
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn(AppLanguage $language): array => $this->languagePayload($language))
                ->values(),
            'activeLanguages' => AppLanguage::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn(AppLanguage $language): array => $this->languagePayload($language))
                ->values(),
            'legalTypes' => AppLegalDocument::TYPES,
            'stats' => [
                'keys' => AppTranslationKey::query()->count(),
                'languages' => AppLanguage::query()->where('is_active', true)->count(),
                'legalDocuments' => AppLegalDocument::query()->count(),
                'faqItems' => AppFaqItem::query()->count(),
            ],
        ]);
    }

    public function apiTranslationKeysIndex(Request $request): JsonResponse
    {
        $activeLanguages = $this->activeLanguages();
        $selectedLanguageId = (int) ($request->query('language_id') ?: ($activeLanguages->firstWhere('is_default', true)?->id ?? $activeLanguages->first()?->id));
        $archiveMode = $request->query('archive') === 'archived';

        $query = AppTranslationKey::query()
            ->when($archiveMode, fn($query) => $query->onlyTrashed())
            ->with(['translations.language']);

        $this->applyKeyFilters($query, $request, $selectedLanguageId);

        $keys = $query
            ->orderBy('category')
            ->orderBy('screen')
            ->orderBy('key')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $folderTree = $this->folderTree($archiveMode, $request, $selectedLanguageId);

        return $this->jsonSuccess([
            'items' => $keys->getCollection()
                ->map(fn(AppTranslationKey $translationKey): array => $this->translationKeyPayload($translationKey, $activeLanguages))
                ->values(),
            'pagination' => $this->paginationPayload($keys),
            'filters' => [
                'categories' => array_keys($folderTree),
                'screens' => collect($folderTree)->flatMap(fn(array $category): array => array_keys($category['screens']))->unique()->values(),
                'folderTree' => $folderTree,
                'selectedLanguageId' => $selectedLanguageId,
            ],
        ]);
    }

    public function apiTranslationKeyShow(int $translationKey): JsonResponse
    {
        $translationKey = AppTranslationKey::withTrashed()
            ->with(['translations.language'])
            ->findOrFail($translationKey);

        return $this->jsonSuccess($this->translationKeyDetailPayload($translationKey));
    }

    public function apiTranslationKeyStore(Request $request): JsonResponse
    {
        $validated = $this->validatedJson($request, [
            'key' => ['required', 'string', 'max:255', 'unique:app_translation_keys,key'],
            'default_value' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'screen' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $translationKey = AppTranslationKey::query()->create([
            'key' => $validated['key'],
            'default_value' => $validated['default_value'] ?? '',
            'category' => $validated['category'] ?? null,
            'screen' => $validated['screen'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);
        $fallback = (string) ($validated['default_value'] ?? '');

        $this->activeLanguages()->each(function (AppLanguage $language) use ($translationKey, $fallback): void {
            AppTranslation::query()->firstOrCreate(
                [
                    'app_translation_key_id' => $translationKey->id,
                    'app_language_id' => $language->id,
                ],
                [
                    'value' => $fallback,
                    'is_active' => true,
                ]
            );
        });

        return $this->jsonSuccess($this->translationKeyDetailPayload($translationKey->fresh(['translations.language'])), 'Translation key eklendi.', 201);
    }

    public function apiTranslationKeyUpdate(Request $request, int $translationKey): JsonResponse
    {
        $translationKey = AppTranslationKey::withTrashed()->findOrFail($translationKey);
        if ($translationKey->trashed()) {
            return $this->jsonError('Arsivdeki key duzenlenemez.', [], 409);
        }

        $validated = $this->validatedJson($request, [
            'key' => ['required', 'string', 'max:255', Rule::unique('app_translation_keys', 'key')->ignore($translationKey->id)],
            'default_value' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'screen' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $translationKey->update([
            'key' => $validated['key'],
            'default_value' => $validated['default_value'] ?? '',
            'category' => $validated['category'] ?? null,
            'screen' => $validated['screen'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return $this->jsonSuccess($this->translationKeyDetailPayload($translationKey->fresh(['translations.language'])), 'Translation key guncellendi.');
    }

    public function apiTranslationsUpdate(Request $request, int $translationKey): JsonResponse
    {
        $translationKey = AppTranslationKey::withTrashed()->findOrFail($translationKey);
        if ($translationKey->trashed()) {
            return $this->jsonError('Arsivdeki key cevirileri duzenlenemez.', [], 409);
        }

        $validated = $this->validatedJson($request, [
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.app_language_id' => ['required', 'exists:app_languages,id'],
            'translations.*.value' => ['nullable', 'string'],
            'translations.*.is_active' => ['sometimes', 'boolean'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        foreach ($validated['translations'] as $translation) {
            AppTranslation::query()->updateOrCreate(
                [
                    'app_translation_key_id' => $translationKey->id,
                    'app_language_id' => $translation['app_language_id'],
                ],
                [
                    'value' => $translation['value'] ?? '',
                    'is_active' => (bool) ($translation['is_active'] ?? true),
                ]
            );
        }

        return $this->jsonSuccess($this->translationKeyDetailPayload($translationKey->fresh(['translations.language'])), 'Ceviriler guncellendi.');
    }

    public function apiTranslationKeyDestroy(int $translationKey): JsonResponse
    {
        $translationKey = AppTranslationKey::query()->findOrFail($translationKey);
        $translationKey->delete();

        return $this->jsonSuccess($this->translationKeyPayload($translationKey), 'Translation key arsivlendi.');
    }

    public function apiTranslationKeyRestore(int $translationKey): JsonResponse
    {
        $translationKey = AppTranslationKey::withTrashed()->findOrFail($translationKey);
        $translationKey->restore();

        return $this->jsonSuccess($this->translationKeyDetailPayload($translationKey->fresh(['translations.language'])), 'Translation key arsivden geri alindi.');
    }

    public function apiTranslationKeyForceDestroy(int $translationKey): JsonResponse
    {
        $translationKey = AppTranslationKey::withTrashed()->findOrFail($translationKey);
        $id = $translationKey->id;
        $translationKey->forceDelete();

        return $this->jsonSuccess(['id' => $id], 'Translation key kalici silindi.');
    }

    public function apiLanguagesIndex(Request $request): JsonResponse
    {
        $archiveMode = $request->query('archive') === 'archived';
        $query = AppLanguage::query()
            ->when($archiveMode, fn($query) => $query->onlyTrashed())
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(fn($inner) => $inner
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('native_name', 'like', "%{$search}%"));
            })
            ->when($request->query('status') === 'active', fn($query) => $query->where('is_active', true))
            ->when($request->query('status') === 'passive', fn($query) => $query->where('is_active', false))
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name');

        $languages = $query->paginate($this->perPage($request))->withQueryString();

        return $this->jsonSuccess([
            'items' => $languages->getCollection()->map(fn(AppLanguage $language): array => $this->languagePayload($language))->values(),
            'pagination' => $this->paginationPayload($languages),
        ]);
    }

    public function apiLanguageShow(int $language): JsonResponse
    {
        return $this->jsonSuccess($this->languagePayload(AppLanguage::withTrashed()->findOrFail($language)));
    }

    public function apiLanguageStore(Request $request): JsonResponse
    {
        $validated = $this->validatedJson($request, $this->languageRules());
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $validated['code'] = strtolower($validated['code']);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($validated['is_default']) {
            AppLanguage::query()->update(['is_default' => false]);
            $validated['is_active'] = true;
        }

        $language = AppLanguage::query()->create($validated);

        return $this->jsonSuccess($this->languagePayload($language), 'Dil eklendi.', 201);
    }

    public function apiLanguageUpdate(Request $request, int $language): JsonResponse
    {
        $language = AppLanguage::withTrashed()->findOrFail($language);
        if ($language->trashed()) {
            return $this->jsonError('Arsivdeki dil duzenlenemez.', [], 409);
        }

        $validated = $this->validatedJson($request, $this->languageRules($language->id, false));
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $language->update([
            'code' => strtolower($validated['code']),
            'name' => $validated['name'],
            'native_name' => $validated['native_name'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $language->is_default ? true : (bool) ($validated['is_active'] ?? false),
        ]);

        return $this->jsonSuccess($this->languagePayload($language->fresh()), 'Dil guncellendi.');
    }

    public function apiLanguageMakeDefault(int $language): JsonResponse
    {
        $language = AppLanguage::withTrashed()->findOrFail($language);
        if ($language->trashed()) {
            return $this->jsonError('Arsivdeki dil varsayilan yapilamaz.', [], 409);
        }

        AppLanguage::query()->update(['is_default' => false]);
        $language->update(['is_default' => true, 'is_active' => true]);

        return $this->jsonSuccess($this->languagePayload($language->fresh()), 'Varsayilan dil guncellendi.');
    }

    public function apiLanguageDestroy(int $language): JsonResponse
    {
        $language = AppLanguage::query()->findOrFail($language);
        if ($language->is_default) {
            return $this->jsonError('Varsayilan dil arsivlenemez.', ['language' => ['Varsayilan dil arsivlenemez.']], 422);
        }

        $language->delete();

        return $this->jsonSuccess($this->languagePayload($language), 'Dil arsivlendi.');
    }

    public function apiLanguageRestore(int $language): JsonResponse
    {
        $language = AppLanguage::withTrashed()->findOrFail($language);
        $language->restore();

        return $this->jsonSuccess($this->languagePayload($language->fresh()), 'Dil arsivden geri alindi.');
    }

    public function apiLanguageForceDestroy(int $language): JsonResponse
    {
        $language = AppLanguage::withTrashed()->findOrFail($language);
        if ($language->is_default) {
            return $this->jsonError('Varsayilan dil kalici silinemez.', ['language' => ['Varsayilan dil kalici silinemez.']], 422);
        }

        $id = $language->id;
        $language->forceDelete();

        return $this->jsonSuccess(['id' => $id], 'Dil kalici silindi.');
    }

    public function apiLegalDocumentsIndex(Request $request): JsonResponse
    {
        $archiveMode = $request->query('archive') === 'archived';
        $query = AppLegalDocument::query()
            ->with('language')
            ->when($archiveMode, fn($query) => $query->onlyTrashed())
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(fn($inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%"));
            })
            ->when($request->filled('type'), fn($query) => $query->where('type', $request->query('type')))
            ->when($request->filled('language_id'), fn($query) => $query->where('app_language_id', $request->query('language_id')))
            ->when($request->query('status') === 'active', fn($query) => $query->where('is_active', true))
            ->when($request->query('status') === 'passive', fn($query) => $query->where('is_active', false))
            ->orderBy('type')
            ->orderBy('app_language_id');

        $documents = $query->paginate($this->perPage($request))->withQueryString();

        return $this->jsonSuccess([
            'items' => $documents->getCollection()->map(fn(AppLegalDocument $document): array => $this->legalDocumentPayload($document))->values(),
            'pagination' => $this->paginationPayload($documents),
            'types' => AppLegalDocument::TYPES,
        ]);
    }

    public function apiLegalDocumentShow(int $legalDocument): JsonResponse
    {
        return $this->jsonSuccess($this->legalDocumentPayload(AppLegalDocument::withTrashed()->with('language')->findOrFail($legalDocument)));
    }

    public function apiLegalDocumentStore(Request $request): JsonResponse
    {
        $validated = $this->validatedJson($request, $this->legalDocumentRules());
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $document = AppLegalDocument::withTrashed()->updateOrCreate(
            [
                'type' => $validated['type'],
                'app_language_id' => $validated['app_language_id'],
            ],
            [
                'title' => $validated['title'],
                'content' => $validated['content'] ?? '',
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]
        );
        if ($document->trashed()) {
            $document->restore();
        }

        return $this->jsonSuccess($this->legalDocumentPayload($document->fresh('language')), 'Yasal metin kaydedildi.', 201);
    }

    public function apiLegalDocumentUpdate(Request $request, int $legalDocument): JsonResponse
    {
        $document = AppLegalDocument::withTrashed()->findOrFail($legalDocument);
        if ($document->trashed()) {
            return $this->jsonError('Arsivdeki yasal metin duzenlenemez.', [], 409);
        }

        $validated = $this->validatedJson($request, $this->legalDocumentRules());
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $document->update([
            'type' => $validated['type'],
            'app_language_id' => $validated['app_language_id'],
            'title' => $validated['title'],
            'content' => $validated['content'] ?? '',
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return $this->jsonSuccess($this->legalDocumentPayload($document->fresh('language')), 'Yasal metin guncellendi.');
    }

    public function apiLegalDocumentDestroy(int $legalDocument): JsonResponse
    {
        $document = AppLegalDocument::query()->findOrFail($legalDocument);
        $document->delete();

        $document->loadMissing('language');

        return $this->jsonSuccess($this->legalDocumentPayload($document), 'Yasal metin arsivlendi.');
    }

    public function apiLegalDocumentRestore(int $legalDocument): JsonResponse
    {
        $document = AppLegalDocument::withTrashed()->findOrFail($legalDocument);
        $document->restore();

        return $this->jsonSuccess($this->legalDocumentPayload($document->fresh('language')), 'Yasal metin arsivden geri alindi.');
    }

    public function apiLegalDocumentForceDestroy(int $legalDocument): JsonResponse
    {
        $document = AppLegalDocument::withTrashed()->findOrFail($legalDocument);
        $document->forceDelete();

        return $this->jsonSuccess(['id' => $legalDocument], 'Yasal metin kalici silindi.');
    }

    public function apiFaqItemsIndex(Request $request): JsonResponse
    {
        $archiveMode = $request->query('archive') === 'archived';
        $query = AppFaqItem::query()
            ->with('language')
            ->when($archiveMode, fn($query) => $query->onlyTrashed())
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(fn($inner) => $inner
                    ->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%"));
            })
            ->when($request->filled('language_id'), fn($query) => $query->where('app_language_id', $request->query('language_id')))
            ->when($request->filled('category'), fn($query) => $this->applyNullableTextFilter($query, 'category', (string) $request->query('category')))
            ->when($request->filled('screen'), fn($query) => $this->applyNullableTextFilter($query, 'screen', (string) $request->query('screen')))
            ->when($request->query('status') === 'active', fn($query) => $query->where('is_active', true))
            ->when($request->query('status') === 'passive', fn($query) => $query->where('is_active', false))
            ->orderBy('app_language_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        $items = $query->paginate($this->perPage($request))->withQueryString();

        return $this->jsonSuccess([
            'items' => $items->getCollection()->map(fn(AppFaqItem $faqItem): array => $this->faqItemPayload($faqItem))->values(),
            'pagination' => $this->paginationPayload($items),
        ]);
    }

    public function apiFaqItemShow(int $faqItem): JsonResponse
    {
        return $this->jsonSuccess($this->faqItemPayload(AppFaqItem::withTrashed()->with('language')->findOrFail($faqItem)));
    }

    public function apiFaqItemStore(Request $request): JsonResponse
    {
        $validated = $this->validatedJson($request, $this->faqRules());
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $faqItem = AppFaqItem::query()->create([
            ...$validated,
            'answer' => $validated['answer'] ?? '',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return $this->jsonSuccess($this->faqItemPayload($faqItem->fresh('language')), 'FAQ eklendi.', 201);
    }

    public function apiFaqItemUpdate(Request $request, int $faqItem): JsonResponse
    {
        $faqItem = AppFaqItem::withTrashed()->findOrFail($faqItem);
        if ($faqItem->trashed()) {
            return $this->jsonError('Arsivdeki FAQ duzenlenemez.', [], 409);
        }

        $validated = $this->validatedJson($request, $this->faqRules());
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $faqItem->update([
            ...$validated,
            'answer' => $validated['answer'] ?? '',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return $this->jsonSuccess($this->faqItemPayload($faqItem->fresh('language')), 'FAQ guncellendi.');
    }

    public function apiFaqItemDestroy(int $faqItem): JsonResponse
    {
        $faqItem = AppFaqItem::query()->findOrFail($faqItem);
        $faqItem->delete();

        $faqItem->loadMissing('language');

        return $this->jsonSuccess($this->faqItemPayload($faqItem), 'FAQ arsivlendi.');
    }

    public function apiFaqItemRestore(int $faqItem): JsonResponse
    {
        $faqItem = AppFaqItem::withTrashed()->findOrFail($faqItem);
        $faqItem->restore();

        return $this->jsonSuccess($this->faqItemPayload($faqItem->fresh('language')), 'FAQ arsivden geri alindi.');
    }

    public function apiFaqItemForceDestroy(int $faqItem): JsonResponse
    {
        $faqItem = AppFaqItem::withTrashed()->findOrFail($faqItem);
        $id = $faqItem->id;
        $faqItem->forceDelete();

        return $this->jsonSuccess(['id' => $id], 'FAQ kalici silindi.');
    }

    private function jsonSuccess(mixed $data = [], string $message = 'Tamam.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    private function jsonError(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    private function validatedJson(Request $request, array $rules): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->jsonError('Dogrulama hatasi.', $validator->errors()->toArray(), 422);
        }

        return $validator->validated();
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 30), 1), 100);
    }

    private function paginationPayload($paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function activeLanguages()
    {
        return AppLanguage::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function languageRules(?int $ignoreId = null, bool $allowDefault = true): array
    {
        return [
            'code' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/i', Rule::unique('app_languages', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => [$allowDefault ? 'sometimes' : 'prohibited', 'boolean'],
        ];
    }

    private function legalDocumentRules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(AppLegalDocument::TYPES))],
            'app_language_id' => ['required', 'exists:app_languages,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function faqRules(): array
    {
        return [
            'app_language_id' => ['required', 'exists:app_languages,id'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'screen' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function languagePayload(AppLanguage $language): array
    {
        return [
            'id' => $language->id,
            'code' => $language->code,
            'name' => $language->name,
            'native_name' => $language->native_name,
            'sort_order' => $language->sort_order,
            'is_active' => (bool) $language->is_active,
            'is_default' => (bool) $language->is_default,
            'is_archived' => $language->trashed(),
            'updated_at' => $language->updated_at?->toIso8601String(),
        ];
    }

    private function translationKeyPayload(AppTranslationKey $translationKey, mixed $activeLanguages = null): array
    {
        $translationKey->loadMissing('translations');
        $activeLanguages ??= $this->activeLanguages();

        return [
            'id' => $translationKey->id,
            'key' => $translationKey->key,
            'default_value' => $translationKey->default_value,
            'category' => $this->folderLabelFor($translationKey->category),
            'screen' => $this->folderLabelFor($translationKey->screen),
            'raw_category' => $translationKey->category,
            'raw_screen' => $translationKey->screen,
            'is_active' => (bool) $translationKey->is_active,
            'is_archived' => $translationKey->trashed(),
            'missing_count' => $this->missingTranslationCount($translationKey, $activeLanguages),
            'updated_at' => $translationKey->updated_at?->toIso8601String(),
        ];
    }

    private function translationKeyDetailPayload(AppTranslationKey $translationKey): array
    {
        $activeLanguages = $this->activeLanguages();
        $translationKey->loadMissing(['translations.language']);

        return [
            'item' => $this->translationKeyPayload($translationKey, $activeLanguages),
            'translations' => $activeLanguages->map(function (AppLanguage $language) use ($translationKey): array {
                $translation = $translationKey->translations->firstWhere('app_language_id', $language->id);

                return [
                    'id' => $translation?->id,
                    'app_language_id' => $language->id,
                    'language_code' => $language->code,
                    'language_name' => $language->name,
                    'value' => $translation?->value ?? '',
                    'is_active' => (bool) ($translation?->is_active ?? true),
                    'is_missing' => ! $translation || ! $translation->is_active || trim((string) $translation->value) === '',
                    'updated_at' => $translation?->updated_at?->toIso8601String(),
                ];
            })->values(),
        ];
    }

    private function missingTranslationCount(AppTranslationKey $translationKey, mixed $activeLanguages): int
    {
        return $activeLanguages->filter(function (AppLanguage $language) use ($translationKey): bool {
            $translation = $translationKey->translations->firstWhere('app_language_id', $language->id);

            return ! $translation || ! $translation->is_active || trim((string) $translation->value) === '';
        })->count();
    }

    private function legalDocumentPayload(AppLegalDocument $document): array
    {
        $document->loadMissing('language');

        return [
            'id' => $document->id,
            'type' => $document->type,
            'type_label' => AppLegalDocument::TYPES[$document->type] ?? $document->type,
            'app_language_id' => $document->app_language_id,
            'language_code' => $document->language?->code,
            'language_name' => $document->language?->name,
            'title' => $document->title,
            'content' => $document->content,
            'is_active' => (bool) $document->is_active,
            'is_archived' => $document->trashed(),
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }

    private function faqItemPayload(AppFaqItem $faqItem): array
    {
        $faqItem->loadMissing('language');

        return [
            'id' => $faqItem->id,
            'app_language_id' => $faqItem->app_language_id,
            'language_code' => $faqItem->language?->code,
            'language_name' => $faqItem->language?->name,
            'question' => $faqItem->question,
            'answer' => $faqItem->answer,
            'category' => $this->folderLabelFor($faqItem->category),
            'screen' => $this->folderLabelFor($faqItem->screen),
            'raw_category' => $faqItem->category,
            'raw_screen' => $faqItem->screen,
            'sort_order' => $faqItem->sort_order,
            'is_active' => (bool) $faqItem->is_active,
            'is_archived' => $faqItem->trashed(),
            'updated_at' => $faqItem->updated_at?->toIso8601String(),
        ];
    }

    private function applyKeyFilters($query, Request $request, int $selectedLanguageId, bool $includeFolderFilters = true): void
    {
        $query
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($inner) use ($search): void {
                    $inner->where('key', 'like', "%{$search}%")
                        ->orWhere('default_value', 'like', "%{$search}%")
                        ->orWhereHas('translations', fn($translationQuery) => $translationQuery->where('value', 'like', "%{$search}%"));
                });
            })
            ->when($includeFolderFilters && $request->filled('category'), fn($query) => $this->applyNullableTextFilter($query, 'category', (string) $request->query('category')))
            ->when($includeFolderFilters && $request->filled('screen'), fn($query) => $this->applyNullableTextFilter($query, 'screen', (string) $request->query('screen')))
            ->when($request->query('status') === 'active', fn($query) => $query->where('is_active', true))
            ->when($request->query('status') === 'passive', fn($query) => $query->where('is_active', false))
            ->when($request->boolean('missing') && $selectedLanguageId > 0, function ($query) use ($selectedLanguageId): void {
                $query->whereDoesntHave('translations', function ($translationQuery) use ($selectedLanguageId): void {
                    $translationQuery
                        ->where('app_language_id', $selectedLanguageId)
                        ->where('is_active', true)
                        ->whereNotNull('value')
                        ->where('value', '!=', '');
                });
            });
    }

    private function applyNullableTextFilter($query, string $column, string $value): void
    {
        if ($value === '__general') {
            $query->where(function ($inner) use ($column): void {
                $inner->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        $query->where($column, $value);
    }

    private function folderTree(bool $archiveMode, Request $request, int $selectedLanguageId): array
    {
        $query = AppTranslationKey::query()
            ->when($archiveMode, fn($query) => $query->onlyTrashed());

        $this->applyKeyFilters($query, $request, $selectedLanguageId, false);

        $items = $query->get(['category', 'screen']);
        $tree = [];

        foreach ($items as $item) {
            $category = $this->folderLabelFor($item->category);
            $screen = $this->folderLabelFor($item->screen);
            $tree[$category] ??= ['count' => 0, 'screens' => []];
            $tree[$category]['count']++;
            $tree[$category]['screens'][$screen] = ($tree[$category]['screens'][$screen] ?? 0) + 1;
        }

        ksort($tree);
        foreach ($tree as &$category) {
            ksort($category['screens']);
        }

        return $tree;
    }

    private function folderLabelFor(?string $value): string
    {
        return trim((string) $value) !== '' ? (string) $value : 'Genel';
    }

    private function folderFilterValue(string $label): string
    {
        return $label === 'Genel' ? '__general' : $label;
    }

    private function folderLabelFromFilter(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value === '__general' ? 'Genel' : (string) $value;
    }

    private function faqValidation(Request $request): array
    {
        $validated = $request->validate([
            'app_language_id' => ['required', 'exists:app_languages,id'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'screen' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }
}
