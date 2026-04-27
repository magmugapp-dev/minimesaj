<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAyar;
use App\Models\AiConversationState;
use App\Models\AiEngineConfig;
use App\Models\AiGuardrailRule;
use App\Models\AiMemory;
use App\Models\AiPersonaProfile;
use App\Models\AiTurnLog;
use App\Models\User;
use App\Services\AyarServisi;
use App\Services\Media\UserProfilePhotoService;
use App\Services\Users\UserAvailabilityScheduleService;
use App\Services\Users\UserOnlineStatusService;
use App\Services\YapayZeka\V2\AiEngineConfigService;
use App\Services\YapayZeka\V2\AiPersonaService;
use App\Support\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Illuminate\View\View;
use Throwable;

class AiStudioController extends Controller
{
    public function __construct(
        private ?AiEngineConfigService $engineConfigService = null,
        private ?AiPersonaService $personaService = null,
        private ?UserAvailabilityScheduleService $availabilityScheduleService = null,
        private ?UserOnlineStatusService $userOnlineStatusService = null,
        private ?UserProfilePhotoService $profilePhotoService = null,
        private ?AyarServisi $ayarServisi = null,
    ) {
        $this->engineConfigService ??= app(AiEngineConfigService::class);
        $this->personaService ??= app(AiPersonaService::class);
        $this->availabilityScheduleService ??= app(UserAvailabilityScheduleService::class);
        $this->userOnlineStatusService ??= app(UserOnlineStatusService::class);
        $this->profilePhotoService ??= app(UserProfilePhotoService::class);
        $this->ayarServisi ??= app(AyarServisi::class);
    }

    public function index(Request $request): View
    {
        $config = $this->engineConfigService->activeConfig();
        $search = trim((string) $request->string('q'));

        $personalar = User::query()
            ->where('hesap_tipi', 'ai')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('ad', 'like', '%' . $search . '%')
                        ->orWhere('soyad', 'like', '%' . $search . '%')
                        ->orWhere('kullanici_adi', 'like', '%' . $search . '%')
                        ->orWhere('biyografi', 'like', '%' . $search . '%');
                });
            })
            ->withCount(['fotograflar as fotograf_sayisi' => fn($query) => $query->where('medya_tipi', 'fotograf')])
            ->with(['aiPersonaProfile.engineConfig'])
            ->orderBy('ad')
            ->get()
            ->map(function (User $user) {
                $user->setRelation('aiPersonaProfile', $this->personaService->ensureForUser($user));

                return $user;
            });

        $istatistikler = [
            'persona_sayisi' => $personalar->count(),
            'aktif_persona' => $personalar->filter(fn(User $user) => $user->aiPersonaProfile?->aktif_mi)->count(),
            'aktif_state' => AiConversationState::query()->whereIn('ai_durumu', ['typing', 'queued'])->count(),
            'bugunku_turn' => AiTurnLog::query()->whereDate('created_at', today())->count(),
        ];

        $sonTraceler = AiTurnLog::query()
            ->with('aiUser:id,ad,soyad')
            ->latest()
            ->limit(15)
            ->get();

        return view('admin.ai-v2.index', array_merge($this->sharedViewData(), [
            'config' => $config,
            'personalar' => $personalar,
            'istatistikler' => $istatistikler,
            'blockedTopicsText' => $this->rulesToText($config, 'blocked_topic'),
            'requiredRulesText' => $this->rulesToText($config, 'required_rule'),
            'sonTraceler' => $sonTraceler,
            'search' => $search,
        ]));
    }

    public function create(): View
    {
        return view('admin.ai-v2.create', array_merge($this->sharedViewData(), [
            'kullanici' => null,
            'persona' => null,
            'blockedTopicsText' => '',
            'requiredRulesText' => '',
            'scheduleRows' => [],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validateStudioPayload($request, true);

        $kullanici = DB::transaction(function () use ($payload) {
            $user = User::query()->create($payload['user']);

            $persona = AiPersonaProfile::query()->create($payload['persona'] + [
                'ai_user_id' => $user->id,
            ]);

            $this->mirrorLegacySettings($user, $persona, $payload['legacy']);

            $this->replaceRules(
                aiEngineConfig: null,
                aiPersonaProfile: $persona,
                blockedTopics: $payload['guardrails']['blocked_topics'],
                requiredRules: $payload['guardrails']['required_rules'],
            );

            $this->availabilityScheduleService->replaceForUser($user, $payload['schedules']);
            $this->userOnlineStatusService->sync($user->fresh(['aiAyar', 'availabilitySchedules']));

            return $user;
        });

        $photoErrors = $this->uploadPhotosFromRequest($request, $kullanici);

        $redirect = redirect()
            ->route('admin.ai.goster', $kullanici)
            ->with('basari', $kullanici->ad . ' AI persona kaydi olusturuldu.');

        if ($photoErrors !== []) {
            $redirect->with('hatalar', $photoErrors);
        }

        return $redirect;
    }

    public function engineUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'aktif_mi' => 'nullable|boolean',
            'model_adi' => ['required', Rule::in(array_keys(config('ai_studio_dropdowns.models', [])))],
            'temperature' => 'required|numeric|min:0|max:2',
            'top_p' => 'required|numeric|min:0|max:1',
            'max_output_tokens' => 'required|integer|min:64|max:8192',
            'sistem_komutu' => 'nullable|string|max:8000',
            'blocked_topics' => 'nullable|string|max:4000',
            'required_rules' => 'nullable|string|max:4000',
        ]);

        $config = $this->engineConfigService->activeConfig();
        $config->update([
            'aktif_mi' => $request->boolean('aktif_mi', true),
            'saglayici_tipi' => 'gemini',
            'model_adi' => $validated['model_adi'],
            'temperature' => $validated['temperature'],
            'top_p' => $validated['top_p'],
            'max_output_tokens' => $validated['max_output_tokens'],
            'sistem_komutu' => $validated['sistem_komutu'] ?? null,
        ]);

        $this->replaceRules(
            aiEngineConfig: $config,
            aiPersonaProfile: null,
            blockedTopics: $this->textToLines($validated['blocked_topics'] ?? null),
            requiredRules: $this->textToLines($validated['required_rules'] ?? null),
        );

        return back()->with('basari', 'AI Engine V2 ayarlari guncellendi.');
    }

    public function show(User $kullanici): View
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $persona = $this->personaService->ensureForUser($kullanici);
        $kullanici->loadMissing(['fotograflar' => fn($query) => $query->orderBy('sira_no')->orderBy('id')]);

        return view('admin.ai-v2.show', array_merge($this->sharedViewData(), [
            'kullanici' => $kullanici,
            'persona' => $persona,
            'states' => AiConversationState::query()
                ->where('ai_user_id', $kullanici->id)
                ->latest('durum_guncellendi_at')
                ->limit(20)
                ->get(),
            'memories' => AiMemory::query()
                ->where('ai_user_id', $kullanici->id)
                ->latest()
                ->limit(20)
                ->get(),
            'traces' => AiTurnLog::query()
                ->where('ai_user_id', $kullanici->id)
                ->latest()
                ->limit(25)
                ->get(),
            'blockedTopicsText' => $this->rulesToText($persona, 'blocked_topic'),
            'requiredRulesText' => $this->rulesToText($persona, 'required_rule'),
        ]));
    }

    public function edit(User $kullanici): View
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $kullanici->loadMissing('availabilitySchedules');
        $kullanici->loadMissing(['fotograflar' => fn($query) => $query->orderBy('sira_no')->orderBy('id')]);
        $persona = $this->personaService->ensureForUser($kullanici);

        return view('admin.ai-v2.edit', array_merge($this->sharedViewData(), [
            'kullanici' => $kullanici,
            'persona' => $persona,
            'blockedTopicsText' => $this->rulesToText($persona, 'blocked_topic'),
            'requiredRulesText' => $this->rulesToText($persona, 'required_rule'),
            'scheduleRows' => $this->availabilityScheduleService->formRowsForUser($kullanici),
        ]));
    }

    public function update(Request $request, User $kullanici): RedirectResponse
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $persona = $this->personaService->ensureForUser($kullanici);
        $payload = $this->validateStudioPayload($request, false, $kullanici, $persona);

        DB::transaction(function () use ($kullanici, $persona, $payload) {
            $kullanici->update($payload['user']);
            $persona->update($payload['persona']);
            $this->mirrorLegacySettings($kullanici, $persona->fresh(), $payload['legacy']);

            $this->replaceRules(
                aiEngineConfig: null,
                aiPersonaProfile: $persona,
                blockedTopics: $payload['guardrails']['blocked_topics'],
                requiredRules: $payload['guardrails']['required_rules'],
            );

            $this->availabilityScheduleService->replaceForUser($kullanici, $payload['schedules']);
            $this->userOnlineStatusService->sync($kullanici->fresh(['aiAyar', 'availabilitySchedules']));
        });

        return redirect()
            ->route('admin.ai.goster', $kullanici)
            ->with('basari', 'Persona override ayarlari guncellendi.');
    }

    public function destroy(User $kullanici): RedirectResponse
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $adSoyad = trim($kullanici->ad . ' ' . $kullanici->soyad);

        DB::transaction(function () use ($kullanici): void {
            $kullanici->delete();
        });

        return redirect()
            ->route('admin.ai.index')
            ->with('basari', ($adSoyad !== '' ? $adSoyad : '@' . $kullanici->kullanici_adi) . ' silindi.');
    }

    public function states(Request $request): View
    {
        $query = AiConversationState::query()->with('aiUser:id,ad,soyad');

        if ($request->filled('ai_user_id')) {
            $query->where('ai_user_id', (int) $request->input('ai_user_id'));
        }

        if ($request->filled('kanal')) {
            $query->where('kanal', $request->input('kanal'));
        }

        return view('admin.ai-v2.states', [
            'states' => $query->latest('durum_guncellendi_at')->paginate(30)->withQueryString(),
            'aiUsers' => User::query()->where('hesap_tipi', 'ai')->orderBy('ad')->get(['id', 'ad', 'soyad']),
        ]);
    }

    public function memories(Request $request): View
    {
        $query = AiMemory::query()->with('aiUser:id,ad,soyad');

        if ($request->filled('ai_user_id')) {
            $query->where('ai_user_id', (int) $request->input('ai_user_id'));
        }

        if ($request->filled('kanal')) {
            $query->where('kanal', $request->input('kanal'));
        }

        return view('admin.ai-v2.memories', [
            'memories' => $query->latest()->paginate(30)->withQueryString(),
            'aiUsers' => User::query()->where('hesap_tipi', 'ai')->orderBy('ad')->get(['id', 'ad', 'soyad']),
        ]);
    }

    public function traces(Request $request): View
    {
        $query = AiTurnLog::query()->with('aiUser:id,ad,soyad');

        if ($request->filled('ai_user_id')) {
            $query->where('ai_user_id', (int) $request->input('ai_user_id'));
        }

        if ($request->filled('kanal')) {
            $query->where('kanal', $request->input('kanal'));
        }

        return view('admin.ai-v2.traces', [
            'traces' => $query->latest()->paginate(30)->withQueryString(),
            'aiUsers' => User::query()->where('hesap_tipi', 'ai')->orderBy('ad')->get(['id', 'ad', 'soyad']),
        ]);
    }

    private function validateStudioPayload(
        Request $request,
        bool $creating,
        ?User $kullanici = null,
        ?AiPersonaProfile $persona = null,
    ): array {
        $dropdowns = $this->dropdowns();
        $countryOptions = array_keys($dropdowns['location_catalog']);
        $languageLabels = array_values($dropdowns['languages']);
        $input = $this->withBehaviorSliderDefaults(
            $request->all(),
            $persona,
            $dropdowns['behavior_sliders']
        );
        $input['availability_schedules'] = $this->availabilityScheduleService->normalizeInput(
            $input['availability_schedules'] ?? []
        );

        $rules = [
            'ad' => 'required|string|max:255',
            'soyad' => 'nullable|string|max:255',
            'kullanici_adi' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'kullanici_adi')->ignore($kullanici?->id),
            ],
            'hesap_durumu' => ['required', Rule::in(array_keys($dropdowns['account_statuses']))],
            'cinsiyet' => ['required', Rule::in(array_keys($dropdowns['genders']))],
            'dogum_yili' => 'nullable|integer|min:1950|max:' . now()->year,
            'biyografi' => 'nullable|string|max:1000',
            'ulke' => ['nullable', Rule::in($countryOptions)],
            'aktif_mi' => 'nullable|boolean',
            'dating_aktif_mi' => 'nullable|boolean',
            'instagram_aktif_mi' => 'nullable|boolean',
            'ilk_mesaj_atar_mi' => 'nullable|boolean',
            'model_adi' => ['required', Rule::in(array_keys($dropdowns['models']))],
            'ilk_mesaj_tonu' => 'nullable|string|max:500',
            'persona_ozeti' => 'nullable|string|max:2000',
            'ana_dil_kodu' => ['required', Rule::in(array_keys($dropdowns['languages']))],
            'ikinci_diller' => 'nullable|array',
            'ikinci_diller.*' => ['string', Rule::in($languageLabels)],
            'persona_ulke' => ['nullable', Rule::in($countryOptions)],
            'persona_bolge' => 'nullable|string|max:120',
            'persona_sehir' => 'nullable|string|max:120',
            'persona_mahalle' => ['nullable', Rule::in($dropdowns['living_environments'])],
            'kulturel_koken' => ['nullable', Rule::in($dropdowns['cultural_origins'])],
            'uyruk' => ['nullable', Rule::in($countryOptions)],
            'yasam_tarzi' => ['nullable', Rule::in($dropdowns['lifestyles'])],
            'meslek' => ['nullable', Rule::in($dropdowns['professions'])],
            'sektor' => ['nullable', Rule::in($dropdowns['sectors'])],
            'egitim' => ['nullable', Rule::in($dropdowns['education_levels'])],
            'okul_bolum' => 'nullable|string|max:220',
            'yas_araligi' => ['nullable', Rule::in($dropdowns['age_ranges'])],
            'gunluk_rutin' => 'nullable|string|max:1500',
            'hobiler' => 'nullable|string|max:1500',
            'sevdigi_mekanlar' => 'nullable|string|max:1500',
            'aile_arkadas_notu' => 'nullable|string|max:1500',
            'iliski_gecmisi_tonu' => ['nullable', Rule::in($dropdowns['relationship_history_tones'])],
            'konusma_imzasi' => 'nullable|string|max:1500',
            'cevap_ritmi' => ['nullable', Rule::in($dropdowns['response_rhythms'])],
            'emoji_aliskanligi' => ['nullable', Rule::in($dropdowns['emoji_habits'])],
            'kacinilacak_persona_detaylari' => 'nullable|string|max:1500',
            'konusma_tonu' => ['nullable', Rule::in(array_keys($dropdowns['conversation_tones']))],
            'konusma_stili' => ['nullable', Rule::in(array_keys($dropdowns['conversation_styles']))],
            'mesaj_uzunlugu_min' => 'required|integer|min:8|max:400',
            'mesaj_uzunlugu_max' => 'required|integer|min:20|max:800',
            'minimum_cevap_suresi_saniye' => 'required|integer|min:0|max:600',
            'maksimum_cevap_suresi_saniye' => 'required|integer|min:0|max:1200',
            'saat_dilimi' => 'nullable|timezone',
            'uyku_baslangic' => 'nullable|date_format:H:i',
            'uyku_bitis' => 'nullable|date_format:H:i',
            'hafta_sonu_uyku_baslangic' => 'nullable|date_format:H:i',
            'hafta_sonu_uyku_bitis' => 'nullable|date_format:H:i',
            'blocked_topics' => 'nullable|string|max:4000',
            'required_rules' => 'nullable|string|max:4000',
            'availability_schedules' => 'nullable|array',
            'availability_schedules.*' => 'nullable|array',
            'fotograflar' => ['nullable', 'array', 'max:' . $this->profilePhotoService->maxPhotos()],
            'fotograflar.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:' . $this->maxPhotoSizeKb()],
        ];

        foreach ($dropdowns['behavior_sliders'] as $field => $meta) {
            $rules[$field] = 'required|integer|min:0|max:10';
        }

        $validator = validator($input, $rules);
        $validator->after(function (Validator $validator) use ($input, $dropdowns): void {
            $this->validateLocationSelection($validator, $input, $dropdowns['location_catalog']);
            $this->availabilityScheduleService->validateRows(
                $validator,
                $input['availability_schedules'] ?? [],
                (string) ($input['saat_dilimi'] ?? config('app.timezone')),
            );
        });

        $validated = $validator->validate();
        $schedules = $this->availabilityScheduleService->sanitizedRows($validated['availability_schedules'] ?? []);

        $anaDilKodu = Language::normalizeCode($validated['ana_dil_kodu'] ?? null) ?: 'tr';
        $anaDilAdi = $dropdowns['languages'][$anaDilKodu] ?? Language::name($anaDilKodu, 'Turkce');
        $personaUlke = $validated['persona_ulke'] ?: ($validated['ulke'] ?? null);
        $personaSehir = $validated['persona_sehir'] ?: null;
        $personaMahalle = $validated['persona_mahalle'] ?: null;
        $ikinciDiller = array_values(array_unique(array_filter($validated['ikinci_diller'] ?? [])));

        $minimumLength = (int) $validated['mesaj_uzunlugu_min'];
        $maximumLength = max($minimumLength, (int) $validated['mesaj_uzunlugu_max']);
        $minimumDelay = (int) $validated['minimum_cevap_suresi_saniye'];
        $maximumDelay = max($minimumDelay, (int) $validated['maksimum_cevap_suresi_saniye']);

        $metadata = array_merge($persona?->metadata ?? [], [
            'model_adi' => $validated['model_adi'],
        ]);

        $userPayload = [
            'ad' => $validated['ad'],
            'soyad' => $validated['soyad'] ?? null,
            'kullanici_adi' => $validated['kullanici_adi'],
            'hesap_tipi' => 'ai',
            'hesap_durumu' => $validated['hesap_durumu'],
            'cinsiyet' => $validated['cinsiyet'],
            'dogum_yili' => $validated['dogum_yili'] ?? null,
            'ulke' => $validated['ulke'] ?? null,
            'il' => $personaSehir,
            'ilce' => $personaMahalle,
            'biyografi' => $validated['biyografi'] ?? null,
            'dil' => $anaDilKodu,
        ];

        if ($creating) {
            $userPayload['password'] = bcrypt(Str::random(32));
        }

        $personaPayload = [
            'ai_engine_config_id' => $this->engineConfigService->activeConfig()->id,
            'aktif_mi' => $request->boolean('aktif_mi', true),
            'dating_aktif_mi' => $request->boolean('dating_aktif_mi', true),
            'instagram_aktif_mi' => $request->boolean('instagram_aktif_mi', true),
            'ilk_mesaj_atar_mi' => $request->boolean('ilk_mesaj_atar_mi', true),
            'ilk_mesaj_tonu' => $validated['ilk_mesaj_tonu'] ?? null,
            'persona_ozeti' => $validated['persona_ozeti'] ?? ($validated['biyografi'] ?? null),
            'ana_dil_kodu' => $anaDilKodu,
            'ana_dil_adi' => $anaDilAdi,
            'ikinci_diller' => $ikinciDiller,
            'persona_ulke' => $personaUlke,
            'persona_bolge' => $validated['persona_bolge'] ?? null,
            'persona_sehir' => $personaSehir,
            'persona_mahalle' => $personaMahalle,
            'kulturel_koken' => $validated['kulturel_koken'] ?? null,
            'uyruk' => $validated['uyruk'] ?? null,
            'yasam_tarzi' => $validated['yasam_tarzi'] ?? null,
            'meslek' => $validated['meslek'] ?? null,
            'sektor' => $validated['sektor'] ?? null,
            'egitim' => $validated['egitim'] ?? null,
            'okul_bolum' => $validated['okul_bolum'] ?? null,
            'yas_araligi' => $validated['yas_araligi'] ?? null,
            'gunluk_rutin' => $validated['gunluk_rutin'] ?? null,
            'hobiler' => $validated['hobiler'] ?? null,
            'sevdigi_mekanlar' => $validated['sevdigi_mekanlar'] ?? null,
            'aile_arkadas_notu' => $validated['aile_arkadas_notu'] ?? null,
            'iliski_gecmisi_tonu' => $validated['iliski_gecmisi_tonu'] ?? null,
            'konusma_imzasi' => $validated['konusma_imzasi'] ?? null,
            'cevap_ritmi' => $validated['cevap_ritmi'] ?? null,
            'emoji_aliskanligi' => $validated['emoji_aliskanligi'] ?? null,
            'kacinilacak_persona_detaylari' => $validated['kacinilacak_persona_detaylari'] ?? null,
            'konusma_tonu' => $validated['konusma_tonu'] ?? null,
            'konusma_stili' => $validated['konusma_stili'] ?? null,
            'mesaj_uzunlugu_min' => $minimumLength,
            'mesaj_uzunlugu_max' => $maximumLength,
            'minimum_cevap_suresi_saniye' => $minimumDelay,
            'maksimum_cevap_suresi_saniye' => $maximumDelay,
            'saat_dilimi' => $validated['saat_dilimi'] ?? config('app.timezone'),
            'uyku_baslangic' => $validated['uyku_baslangic'] ?? null,
            'uyku_bitis' => $validated['uyku_bitis'] ?? null,
            'hafta_sonu_uyku_baslangic' => $validated['hafta_sonu_uyku_baslangic'] ?? null,
            'hafta_sonu_uyku_bitis' => $validated['hafta_sonu_uyku_bitis'] ?? null,
            'metadata' => $metadata,
        ];

        $legacyPayload = [];
        foreach (array_keys($dropdowns['behavior_sliders']) as $field) {
            $personaPayload[$field] = $validated[$field];
            $legacyPayload[$field] = $validated[$field];
        }

        return [
            'user' => $userPayload,
            'persona' => $personaPayload,
            'legacy' => $legacyPayload,
            'guardrails' => [
                'blocked_topics' => $this->textToLines($validated['blocked_topics'] ?? null),
                'required_rules' => $this->textToLines($validated['required_rules'] ?? null),
            ],
            'schedules' => $schedules,
        ];
    }

    private function withBehaviorSliderDefaults(
        array $input,
        ?AiPersonaProfile $persona,
        array $behaviorSliders,
    ): array {
        foreach ($behaviorSliders as $field => $meta) {
            if (array_key_exists($field, $input) && $input[$field] !== null && $input[$field] !== '') {
                continue;
            }

            $input[$field] = $persona?->{$field};

            if ($input[$field] === null || $input[$field] === '') {
                $input[$field] = (int) ($meta['default'] ?? 5);
            }
        }

        return $input;
    }

    private function validateLocationSelection(Validator $validator, array $data, array $catalog): void
    {
        $country = $data['persona_ulke'] ?? null;
        $region = $data['persona_bolge'] ?? null;
        $city = $data['persona_sehir'] ?? null;

        if ($country && ! isset($catalog[$country])) {
            $validator->errors()->add('persona_ulke', 'Secilen ulke katalogda bulunmuyor.');

            return;
        }

        if ($region && ! $country) {
            $validator->errors()->add('persona_ulke', 'Bolge secimi icin once ulke secmelisin.');

            return;
        }

        if ($city && (! $country || ! $region)) {
            $validator->errors()->add('persona_bolge', 'Sehir secimi icin once ulke ve bolge secmelisin.');

            return;
        }

        if ($country && $region) {
            $regions = array_keys($catalog[$country]['regions'] ?? []);

            if (! in_array($region, $regions, true)) {
                $validator->errors()->add('persona_bolge', 'Secilen bolge bu ulke icin gecersiz.');

                return;
            }
        }

        if ($country && $region && $city) {
            $cities = $catalog[$country]['regions'][$region] ?? [];

            if (! in_array($city, $cities, true)) {
                $validator->errors()->add('persona_sehir', 'Secilen sehir bu bolge icin gecersiz.');
            }
        }
    }

    private function mirrorLegacySettings(User $kullanici, AiPersonaProfile $persona, array $legacy): void
    {
        $engineConfig = $this->engineConfigService->activeConfig();
        $dropdowns = $this->dropdowns();

        $ayarlar = [
            'aktif_mi' => $persona->aktif_mi,
            'saglayici_tipi' => 'gemini',
            'model_adi' => data_get($persona->metadata, 'model_adi', $engineConfig->model_adi),
            'kisilik_aciklamasi' => $persona->persona_ozeti,
            'konusma_tonu' => $persona->konusma_tonu,
            'konusma_stili' => $persona->konusma_stili,
            'ilk_mesaj_atar_mi' => $persona->ilk_mesaj_atar_mi,
            'ilk_mesaj_sablonu' => $persona->ilk_mesaj_tonu,
            'minimum_cevap_suresi_saniye' => $persona->minimum_cevap_suresi_saniye,
            'maksimum_cevap_suresi_saniye' => $persona->maksimum_cevap_suresi_saniye,
            'mesaj_uzunlugu_min' => $persona->mesaj_uzunlugu_min,
            'mesaj_uzunlugu_max' => $persona->mesaj_uzunlugu_max,
            'saat_dilimi' => $persona->saat_dilimi,
            'uyku_baslangic' => $persona->uyku_baslangic,
            'uyku_bitis' => $persona->uyku_bitis,
            'hafta_sonu_uyku_baslangic' => $persona->hafta_sonu_uyku_baslangic,
            'hafta_sonu_uyku_bitis' => $persona->hafta_sonu_uyku_bitis,
            'temperature' => $engineConfig->temperature,
            'top_p' => $engineConfig->top_p,
            'max_output_tokens' => $engineConfig->max_output_tokens,
        ];

        foreach (array_keys($dropdowns['behavior_sliders']) as $field) {
            $ayarlar[$field] = $legacy[$field] ?? $persona->{$field} ?? $dropdowns['behavior_sliders'][$field]['default'] ?? 5;
        }

        AiAyar::query()->updateOrCreate(
            ['user_id' => $kullanici->id],
            $ayarlar,
        );

        $metadata = $persona->metadata ?? [];
        $metadata['legacy_ai_ayar_sync'] = now()->toIso8601String();
        $persona->forceFill(['metadata' => $metadata])->save();
    }

    private function sharedViewData(): array
    {
        $dropdowns = $this->dropdowns();
        $behaviorSliders = $dropdowns['behavior_sliders'];

        return [
            'dropdowns' => $dropdowns,
            'modelOptions' => $dropdowns['models'],
            'locationCatalog' => $dropdowns['location_catalog'],
            'countryOptions' => array_keys($dropdowns['location_catalog']),
            'behaviorSliders' => $behaviorSliders,
            'behaviorSliderGroups' => collect($behaviorSliders)->groupBy('group', true)->all(),
            'scheduleStatusOptions' => [
                'active' => 'Aktif',
                'passive' => 'Pasif',
            ],
            'maxPhotos' => $this->profilePhotoService->maxPhotos(),
        ];
    }

    private function uploadPhotosFromRequest(Request $request, User $kullanici): array
    {
        if (! $request->hasFile('fotograflar')) {
            return [];
        }

        $errors = [];

        foreach ($request->file('fotograflar', []) as $file) {
            try {
                $this->profilePhotoService->upload($kullanici, $file);
            } catch (ValidationException $exception) {
                $errors[] = $file->getClientOriginalName() . ': ' . collect($exception->errors())->flatten()->implode(' ');
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = $file->getClientOriginalName() . ': Fotograf yuklenemedi.';
            }
        }

        return $errors;
    }

    private function maxPhotoSizeKb(): int
    {
        $maxPhotoSizeMb = max(1, (int) $this->ayarServisi->al('max_foto_boyut_mb', config('storage.upload.max_image_size_mb', 50)));

        return $maxPhotoSizeMb * 1024;
    }

    private function dropdowns(): array
    {
        return config('ai_studio_dropdowns', []);
    }

    private function replaceRules(
        ?AiEngineConfig $aiEngineConfig,
        ?AiPersonaProfile $aiPersonaProfile,
        array $blockedTopics,
        array $requiredRules,
    ): void {
        AiGuardrailRule::query()
            ->when($aiEngineConfig, fn($query) => $query->where('ai_engine_config_id', $aiEngineConfig->id))
            ->when($aiPersonaProfile, fn($query) => $query->where('ai_persona_profile_id', $aiPersonaProfile->id))
            ->when(! $aiEngineConfig && ! $aiPersonaProfile, fn($query) => $query->whereRaw('1 = 0'))
            ->whereIn('rule_type', ['blocked_topic', 'required_rule'])
            ->delete();

        foreach ($blockedTopics as $topic) {
            AiGuardrailRule::query()->create([
                'ai_engine_config_id' => $aiEngineConfig?->id,
                'ai_persona_profile_id' => $aiPersonaProfile?->id,
                'rule_type' => 'blocked_topic',
                'etiket' => 'Panel Yasakli Konu',
                'icerik' => $topic,
                'severity' => 'block',
                'aktif_mi' => true,
            ]);
        }

        foreach ($requiredRules as $rule) {
            AiGuardrailRule::query()->create([
                'ai_engine_config_id' => $aiEngineConfig?->id,
                'ai_persona_profile_id' => $aiPersonaProfile?->id,
                'rule_type' => 'required_rule',
                'etiket' => 'Panel Zorunlu Kural',
                'icerik' => $rule,
                'severity' => 'enforce',
                'aktif_mi' => true,
            ]);
        }
    }

    private function rulesToText(AiEngineConfig|AiPersonaProfile $owner, string $ruleType): string
    {
        $query = AiGuardrailRule::query()->where('rule_type', $ruleType);

        if ($owner instanceof AiEngineConfig) {
            $query->where('ai_engine_config_id', $owner->id);
        } else {
            $query->where('ai_persona_profile_id', $owner->id);
        }

        return $query->pluck('icerik')->implode("\n");
    }

    private function textToLines(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
    }
}
