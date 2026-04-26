<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAyar;
use App\Models\AiGuardrailRule;
use App\Models\AiPersonaProfile;
use App\Models\InstagramHesap;
use App\Models\User;
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
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class InfluencerController extends Controller
{
    public function __construct(
        private ?AiEngineConfigService $engineConfigService = null,
        private ?AiPersonaService $personaService = null,
        private ?UserAvailabilityScheduleService $availabilityScheduleService = null,
        private ?UserOnlineStatusService $userOnlineStatusService = null,
    ) {
        $this->engineConfigService ??= app(AiEngineConfigService::class);
        $this->personaService ??= app(AiPersonaService::class);
        $this->availabilityScheduleService ??= app(UserAvailabilityScheduleService::class);
        $this->userOnlineStatusService ??= app(UserOnlineStatusService::class);
    }

    public function index(Request $request): View
    {
        $sorgu = User::query()
            ->where('hesap_tipi', 'ai')
            ->whereHas('instagramHesaplari')
            ->with(['aiAyar', 'instagramHesaplari']);

        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('ad', 'like', "%{$arama}%")
                    ->orWhere('soyad', 'like', "%{$arama}%")
                    ->orWhere('kullanici_adi', 'like', "%{$arama}%")
                    ->orWhereHas('instagramHesaplari', function ($q2) use ($arama) {
                        $q2->where('instagram_kullanici_adi', 'like', "%{$arama}%");
                    });
            });
        }

        if ($durum = $request->input('durum')) {
            $sorgu->where('hesap_durumu', $durum);
        }

        if ($request->input('aktif') !== null && $request->input('aktif') !== '') {
            $sorgu->whereHas('aiAyar', function ($q) use ($request) {
                $q->where('aktif_mi', $request->boolean('aktif'));
            });
        }

        $sorgu->orderBy(
            $request->input('sirala', 'created_at'),
            $request->input('yon', 'desc') === 'asc' ? 'asc' : 'desc',
        );

        $influencerlar = $sorgu->paginate(25)->withQueryString();

        $toplamInfluencer = User::query()
            ->where('hesap_tipi', 'ai')
            ->whereHas('instagramHesaplari')
            ->count();

        $istatistikler = [
            'toplam' => $toplamInfluencer,
            'aktif' => User::query()
                ->where('hesap_tipi', 'ai')
                ->where('hesap_durumu', 'aktif')
                ->whereHas('instagramHesaplari')
                ->whereHas('aiAyar', fn ($q) => $q->where('aktif_mi', true))
                ->count(),
            'bagli' => InstagramHesap::query()
                ->whereHas('user', fn ($q) => $q->where('hesap_tipi', 'ai'))
                ->where('aktif_mi', true)
                ->count(),
            'toplam_hesap' => InstagramHesap::query()
                ->whereHas('user', fn ($q) => $q->where('hesap_tipi', 'ai'))
                ->count(),
        ];

        return view('admin.influencer.index', compact('influencerlar', 'istatistikler'));
    }

    public function ekle(): View
    {
        return view('admin.influencer.ekle', array_merge($this->sharedViewData(), [
            'kullanici' => null,
            'persona' => null,
            'instagramHesap' => null,
            'blockedTopicsText' => '',
            'requiredRulesText' => '',
            'scheduleRows' => [],
        ]));
    }

    public function kaydet(Request $request): RedirectResponse
    {
        $payload = $this->validateStudioPayload($request->all(), true);
        $instagramPayload = $this->validateInstagramPayload($request->all(), true);

        $kullanici = DB::transaction(function () use ($payload, $instagramPayload) {
            $user = User::query()->create($payload['user']);

            $persona = AiPersonaProfile::query()->create($payload['persona'] + [
                'ai_user_id' => $user->id,
            ]);

            $this->mirrorLegacySettings($user, $persona, $payload['legacy']);

            $this->replaceRules(
                aiPersonaProfile: $persona,
                blockedTopics: $payload['guardrails']['blocked_topics'],
                requiredRules: $payload['guardrails']['required_rules'],
            );

            $user->instagramHesaplari()->create($instagramPayload);
            $this->availabilityScheduleService->replaceForUser($user, $payload['schedules']);
            $this->userOnlineStatusService->sync($user->fresh(['aiAyar', 'availabilitySchedules']));

            return $user;
        });

        return redirect()
            ->route('admin.influencer.goster', $kullanici)
            ->with('basari', $kullanici->ad . ' influencer hesabi olusturuldu.');
    }

    public function jsonEkle(): View
    {
        $sablon = [
            [
                'ad' => 'Burcin',
                'soyad' => 'Evci',
                'kullanici_adi' => 'burcin_influencer',
                'hesap_durumu' => 'aktif',
                'cinsiyet' => 'kadin',
                'dogum_yili' => 1998,
                'biyografi' => 'Moda, kahve ve sehir hikayelerini seven bir influencer karakteri.',
                'ulke' => 'Turkiye',
                'model_adi' => $this->engineConfigService->activeConfig()->model_adi,
                'aktif_mi' => true,
                'dating_aktif_mi' => false,
                'instagram_aktif_mi' => true,
                'ilk_mesaj_atar_mi' => true,
                'persona_ozeti' => 'Kamerasi acik, sicak ve dogal bir sosyal medya karakteri.',
                'ana_dil_kodu' => 'tr',
                'persona_ulke' => 'Turkiye',
                'persona_bolge' => 'Marmara',
                'persona_sehir' => 'Istanbul',
                'uyruk' => 'Turkiye',
                'meslek' => 'Icerik ureticisi',
                'sektor' => 'Medya',
                'egitim' => 'Lisans',
                'konusma_tonu' => 'samimi',
                'konusma_stili' => 'akici',
                'mesaj_uzunlugu_min' => 18,
                'mesaj_uzunlugu_max' => 180,
                'minimum_cevap_suresi_saniye' => 4,
                'maksimum_cevap_suresi_saniye' => 24,
                'saat_dilimi' => 'Europe/Istanbul',
                'availability_schedules' => [
                    [
                        'date' => now()->addDay()->toDateString(),
                        'start_time' => '09:00',
                        'end_time' => '12:00',
                        'status' => 'active',
                    ],
                    [
                        'date' => now()->addDay()->toDateString(),
                        'start_time' => '22:00',
                        'end_time' => '23:30',
                        'status' => 'passive',
                    ],
                ],
                'instagram_kullanici_adi' => 'burcinprofile',
                'instagram_profil_id' => '17840000000000000',
                'otomatik_cevap_aktif_mi' => true,
                'yarim_otomatik_mod_aktif_mi' => false,
                'instagram_hesap_aktif_mi' => true,
            ],
        ];

        return view('admin.influencer.json-ekle', [
            'sablon' => json_encode($sablon, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function jsonKaydet(Request $request): RedirectResponse
    {
        $request->validate([
            'json_veri' => 'required|string',
        ]);

        $veri = json_decode($request->input('json_veri'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()
                ->withInput()
                ->with('hata', 'Gecersiz JSON formati: ' . json_last_error_msg());
        }

        if (isset($veri['ad'])) {
            $veri = [$veri];
        }

        if (! is_array($veri) || $veri === []) {
            return back()
                ->withInput()
                ->with('hata', 'JSON en az bir influencer kaydi icermelidir.');
        }

        $hatalar = [];
        $olusturulanlar = [];
        $islenenKullaniciAdlari = [];

        DB::beginTransaction();

        try {
            foreach ($veri as $index => $kayit) {
                $sira = $index + 1;

                if (! is_array($kayit)) {
                    $hatalar[] = "#{$sira}: Gecersiz kayit formati.";
                    continue;
                }

                $kullaniciAdi = (string) ($kayit['kullanici_adi'] ?? '?');

                if (in_array($kullaniciAdi, $islenenKullaniciAdlari, true)) {
                    $hatalar[] = "#{$sira} ({$kullaniciAdi}): Bu kullanici adi bu toplu islemde tekrar ediyor.";
                    continue;
                }

                try {
                    $payload = $this->validateStudioPayload($kayit, true);
                    $instagramPayload = $this->validateInstagramPayload($kayit, true);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $hatalar[] = "#{$sira} ({$kullaniciAdi}): " . implode(', ', $e->validator->errors()->all());
                    continue;
                }

                $user = User::query()->create($payload['user']);

                $persona = AiPersonaProfile::query()->create($payload['persona'] + [
                    'ai_user_id' => $user->id,
                ]);

                $this->mirrorLegacySettings($user, $persona, $payload['legacy']);

                $this->replaceRules(
                    aiPersonaProfile: $persona,
                    blockedTopics: $payload['guardrails']['blocked_topics'],
                    requiredRules: $payload['guardrails']['required_rules'],
                );

                $user->instagramHesaplari()->create($instagramPayload);
                $this->availabilityScheduleService->replaceForUser($user, $payload['schedules']);
                $this->userOnlineStatusService->sync($user->fresh(['aiAyar', 'availabilitySchedules']));

                $olusturulanlar[] = $user->kullanici_adi;
                $islenenKullaniciAdlari[] = $user->kullanici_adi;
            }

            if ($olusturulanlar === [] && $hatalar !== []) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('hata', 'Hicbir kayit olusturulamadi.')
                    ->with('hatalar', $hatalar);
            }

            DB::commit();

            $mesaj = count($olusturulanlar) . ' influencer hesabi olusturuldu.';

            if ($hatalar !== []) {
                return redirect()
                    ->route('admin.influencer.index')
                    ->with('basari', $mesaj)
                    ->with('hatalar', $hatalar);
            }

            return redirect()
                ->route('admin.influencer.index')
                ->with('basari', $mesaj);
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('hata', 'Bir hata olustu: ' . $e->getMessage());
        }
    }

    public function goster(User $kullanici): View
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $kullanici->load(['aiAyar', 'instagramHesaplari']);
        $kullanici->loadCount(['eslesmeler']);

        $instagramIstatistikleri = [];
        foreach ($kullanici->instagramHesaplari as $hesap) {
            $instagramIstatistikleri[$hesap->id] = [
                'kisi_sayisi' => $hesap->kisiler()->count(),
                'mesaj_sayisi' => $hesap->mesajlar()->count(),
                'gorev_sayisi' => $hesap->aiGorevleri()->count(),
            ];
        }

        return view('admin.influencer.goster', compact('kullanici', 'instagramIstatistikleri'));
    }

    public function duzenle(User $kullanici): View
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $kullanici->load(['instagramHesaplari', 'availabilitySchedules']);
        $persona = $this->personaService->ensureForUser($kullanici);

        return view('admin.influencer.duzenle', array_merge($this->sharedViewData(), [
            'kullanici' => $kullanici,
            'persona' => $persona,
            'instagramHesap' => $kullanici->instagramHesaplari->first(),
            'blockedTopicsText' => $this->rulesToText($persona, 'blocked_topic'),
            'requiredRulesText' => $this->rulesToText($persona, 'required_rule'),
            'scheduleRows' => $this->availabilityScheduleService->formRowsForUser($kullanici),
        ]));
    }

    public function guncelle(Request $request, User $kullanici): RedirectResponse
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $kullanici->load('instagramHesaplari');
        $persona = $this->personaService->ensureForUser($kullanici);

        $payload = $this->validateStudioPayload($request->all(), false, $kullanici, $persona);
        $instagramHesap = $kullanici->instagramHesaplari->first();
        $instagramPayload = $this->validateInstagramPayload($request->all(), false);

        DB::transaction(function () use ($kullanici, $persona, $payload, $instagramHesap, $instagramPayload) {
            $kullanici->update($payload['user']);
            $persona->update($payload['persona']);

            $this->mirrorLegacySettings($kullanici, $persona->fresh(), $payload['legacy']);

            $this->replaceRules(
                aiPersonaProfile: $persona,
                blockedTopics: $payload['guardrails']['blocked_topics'],
                requiredRules: $payload['guardrails']['required_rules'],
            );

            if ($instagramHesap) {
                $instagramHesap->update($instagramPayload);
            } else {
                $kullanici->instagramHesaplari()->create($instagramPayload);
            }

            $this->availabilityScheduleService->replaceForUser($kullanici, $payload['schedules']);
            $this->userOnlineStatusService->sync($kullanici->fresh(['aiAyar', 'availabilitySchedules']));
        });

        return redirect()
            ->route('admin.influencer.goster', $kullanici)
            ->with('basari', $kullanici->ad . ' influencer hesabi guncellendi.');
    }

    public function destroy(User $kullanici): RedirectResponse
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $adSoyad = trim($kullanici->ad . ' ' . $kullanici->soyad);

        DB::transaction(function () use ($kullanici): void {
            $kullanici->delete();
        });

        return redirect()
            ->route('admin.influencer.index')
            ->with('basari', ($adSoyad !== '' ? $adSoyad : '@' . $kullanici->kullanici_adi) . ' silindi.');
    }

    private function validateStudioPayload(
        array $input,
        bool $creating,
        ?User $kullanici = null,
        ?AiPersonaProfile $persona = null,
    ): array {
        $dropdowns = $this->dropdowns();
        $countryOptions = array_keys($dropdowns['location_catalog']);
        $languageLabels = array_values($dropdowns['languages']);

        $input = $this->normalizeStudioInput($input, $creating, $kullanici, $persona, $dropdowns);
        $input = $this->withBehaviorSliderDefaults($input, $persona, $dropdowns['behavior_sliders']);
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
        $personaUlke = $validated['persona_ulke'] ?? ($validated['ulke'] ?? null);
        $personaSehir = $validated['persona_sehir'] ?? null;
        $personaMahalle = $validated['persona_mahalle'] ?? null;
        $ikinciDiller = array_values(array_unique(array_filter($validated['ikinci_diller'] ?? [])));

        $minimumLength = (int) ($validated['mesaj_uzunlugu_min'] ?? 18);
        $maximumLength = max($minimumLength, (int) ($validated['mesaj_uzunlugu_max'] ?? 220));
        $minimumDelay = (int) ($validated['minimum_cevap_suresi_saniye'] ?? 4);
        $maximumDelay = max($minimumDelay, (int) ($validated['maksimum_cevap_suresi_saniye'] ?? 24));

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
            'aktif_mi' => $this->booleanInput($input, 'aktif_mi', true),
            'dating_aktif_mi' => $this->booleanInput($input, 'dating_aktif_mi', true),
            'instagram_aktif_mi' => $this->booleanInput($input, 'instagram_aktif_mi', true),
            'ilk_mesaj_atar_mi' => $this->booleanInput($input, 'ilk_mesaj_atar_mi', true),
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

        foreach (array_keys($dropdowns['behavior_sliders']) as $field) {
            $personaPayload[$field] = (int) $validated[$field];
        }

        return [
            'user' => $userPayload,
            'persona' => $personaPayload,
            'legacy' => [
                'aktif_mi' => $personaPayload['aktif_mi'],
                'model_adi' => $validated['model_adi'],
                'persona_ozeti' => $personaPayload['persona_ozeti'],
                'konusma_tonu' => $personaPayload['konusma_tonu'],
                'konusma_stili' => $personaPayload['konusma_stili'],
                'ilk_mesaj_atar_mi' => $personaPayload['ilk_mesaj_atar_mi'],
                'ilk_mesaj_tonu' => $personaPayload['ilk_mesaj_tonu'],
                'mesaj_uzunlugu_min' => $minimumLength,
                'mesaj_uzunlugu_max' => $maximumLength,
                'minimum_cevap_suresi_saniye' => $minimumDelay,
                'maksimum_cevap_suresi_saniye' => $maximumDelay,
                'saat_dilimi' => $personaPayload['saat_dilimi'],
                'uyku_baslangic' => $personaPayload['uyku_baslangic'],
                'uyku_bitis' => $personaPayload['uyku_bitis'],
                'hafta_sonu_uyku_baslangic' => $personaPayload['hafta_sonu_uyku_baslangic'],
                'hafta_sonu_uyku_bitis' => $personaPayload['hafta_sonu_uyku_bitis'],
                'emoji_seviyesi' => $personaPayload['emoji_seviyesi'],
                'flort_seviyesi' => $personaPayload['flort_seviyesi'],
                'giriskenlik_seviyesi' => $personaPayload['giriskenlik_seviyesi'],
                'utangaclik_seviyesi' => $personaPayload['utangaclik_seviyesi'],
                'duygusallik_seviyesi' => $personaPayload['duygusallik_seviyesi'],
                'kiskanclik_seviyesi' => $personaPayload['kiskanclik_seviyesi'],
                'mizah_seviyesi' => $personaPayload['mizah_seviyesi'],
                'zeka_seviyesi' => $personaPayload['zeka_seviyesi'],
            ],
            'guardrails' => [
                'blocked_topics' => $this->textToLines($validated['blocked_topics'] ?? null),
                'required_rules' => $this->textToLines($validated['required_rules'] ?? null),
            ],
            'schedules' => $schedules,
        ];
    }

    private function validateInstagramPayload(array $input, bool $creating): array
    {
        $validated = validator($input, [
            'instagram_kullanici_adi' => 'required|string|max:255',
            'instagram_profil_id' => 'nullable|string|max:255',
            'otomatik_cevap_aktif_mi' => 'nullable|boolean',
            'yarim_otomatik_mod_aktif_mi' => 'nullable|boolean',
            'instagram_hesap_aktif_mi' => 'nullable|boolean',
        ])->validate();

        return [
            'instagram_kullanici_adi' => ltrim((string) $validated['instagram_kullanici_adi'], '@'),
            'instagram_profil_id' => $validated['instagram_profil_id'] ?? null,
            'otomatik_cevap_aktif_mi' => $this->booleanInput($input, 'otomatik_cevap_aktif_mi', $creating),
            'yarim_otomatik_mod_aktif_mi' => $this->booleanInput($input, 'yarim_otomatik_mod_aktif_mi', false),
            'aktif_mi' => $this->booleanInput($input, 'instagram_hesap_aktif_mi', true),
        ];
    }

    private function normalizeStudioInput(
        array $input,
        bool $creating,
        ?User $kullanici,
        ?AiPersonaProfile $persona,
        array $dropdowns,
    ): array {
        if (!array_key_exists('hesap_durumu', $input) || $input['hesap_durumu'] === null || $input['hesap_durumu'] === '') {
            $input['hesap_durumu'] = $kullanici?->hesap_durumu ?? 'aktif';
        }

        if (!array_key_exists('model_adi', $input) || $input['model_adi'] === null || $input['model_adi'] === '') {
            $input['model_adi'] = data_get($persona?->metadata, 'model_adi', array_key_first($dropdowns['models']));
        }

        if (!array_key_exists('ulke', $input) || $input['ulke'] === null || $input['ulke'] === '') {
            $input['ulke'] = $kullanici?->ulke ?? 'Turkiye';
        }

        if (!array_key_exists('ana_dil_kodu', $input) || $input['ana_dil_kodu'] === null || $input['ana_dil_kodu'] === '') {
            $input['ana_dil_kodu'] = Language::normalizeCode($persona?->ana_dil_kodu ?? $kullanici?->dil) ?: 'tr';
        }

        if (!array_key_exists('mesaj_uzunlugu_min', $input) || $input['mesaj_uzunlugu_min'] === null || $input['mesaj_uzunlugu_min'] === '') {
            $input['mesaj_uzunlugu_min'] = $persona?->mesaj_uzunlugu_min ?? 18;
        }

        if (!array_key_exists('mesaj_uzunlugu_max', $input) || $input['mesaj_uzunlugu_max'] === null || $input['mesaj_uzunlugu_max'] === '') {
            $input['mesaj_uzunlugu_max'] = $persona?->mesaj_uzunlugu_max ?? 220;
        }

        if (!array_key_exists('minimum_cevap_suresi_saniye', $input) || $input['minimum_cevap_suresi_saniye'] === null || $input['minimum_cevap_suresi_saniye'] === '') {
            $input['minimum_cevap_suresi_saniye'] = $persona?->minimum_cevap_suresi_saniye ?? 4;
        }

        if (!array_key_exists('maksimum_cevap_suresi_saniye', $input) || $input['maksimum_cevap_suresi_saniye'] === null || $input['maksimum_cevap_suresi_saniye'] === '') {
            $input['maksimum_cevap_suresi_saniye'] = $persona?->maksimum_cevap_suresi_saniye ?? 24;
        }

        if (!array_key_exists('saat_dilimi', $input) || $input['saat_dilimi'] === null || $input['saat_dilimi'] === '') {
            $input['saat_dilimi'] = $persona?->saat_dilimi ?? config('app.timezone');
        }

        if (!array_key_exists('persona_ozeti', $input) || $input['persona_ozeti'] === null || $input['persona_ozeti'] === '') {
            $input['persona_ozeti'] = $input['kisilik_aciklamasi'] ?? $kullanici?->biyografi ?? null;
        }

        if (!array_key_exists('ilk_mesaj_tonu', $input) || $input['ilk_mesaj_tonu'] === null || $input['ilk_mesaj_tonu'] === '') {
            $input['ilk_mesaj_tonu'] = $input['ilk_mesaj_sablonu'] ?? null;
        }

        if (!array_key_exists('blocked_topics', $input) || $input['blocked_topics'] === null) {
            $input['blocked_topics'] = $input['yasakli_konular'] ?? null;
        }

        if (!array_key_exists('required_rules', $input) || $input['required_rules'] === null) {
            $input['required_rules'] = $input['zorunlu_kurallar'] ?? null;
        }

        if (!array_key_exists('persona_ulke', $input) || $input['persona_ulke'] === null || $input['persona_ulke'] === '') {
            $input['persona_ulke'] = $input['ulke'] ?? $kullanici?->ulke ?? null;
        }

        if (!array_key_exists('persona_sehir', $input) || $input['persona_sehir'] === null || $input['persona_sehir'] === '') {
            $input['persona_sehir'] = $input['il'] ?? $persona?->persona_sehir ?? $kullanici?->il ?? null;
        }

        if (!array_key_exists('persona_mahalle', $input) || $input['persona_mahalle'] === null || $input['persona_mahalle'] === '') {
            $input['persona_mahalle'] = $persona?->persona_mahalle ?? null;
        }

        if (!array_key_exists('uyruk', $input) || $input['uyruk'] === null || $input['uyruk'] === '') {
            $input['uyruk'] = $input['persona_ulke'] ?? $kullanici?->ulke ?? null;
        }

        if (isset($input['ikinci_diller']) && is_string($input['ikinci_diller'])) {
            $input['ikinci_diller'] = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $input['ikinci_diller']) ?: [])));
        }

        foreach (['blocked_topics', 'required_rules'] as $field) {
            if (isset($input[$field]) && is_array($input[$field])) {
                $input[$field] = implode("\n", array_values(array_filter(array_map(
                    static fn ($value) => is_scalar($value) ? trim((string) $value) : '',
                    $input[$field]
                ))));
            }
        }

        if ($creating) {
            $input['aktif_mi'] = $input['aktif_mi'] ?? true;
            $input['dating_aktif_mi'] = $input['dating_aktif_mi'] ?? true;
            $input['instagram_aktif_mi'] = $input['instagram_aktif_mi'] ?? true;
            $input['ilk_mesaj_atar_mi'] = $input['ilk_mesaj_atar_mi'] ?? true;
        }

        return $input;
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

        if ($country && !isset($catalog[$country])) {
            $validator->errors()->add('persona_ulke', 'Secilen ulke katalogda bulunmuyor.');

            return;
        }

        if ($region && !$country) {
            $validator->errors()->add('persona_ulke', 'Bolge secimi icin once ulke secmelisin.');

            return;
        }

        if ($city && (!$country || !$region)) {
            $validator->errors()->add('persona_bolge', 'Sehir secimi icin once ulke ve bolge secmelisin.');

            return;
        }

        if ($country && $region) {
            $regions = array_keys($catalog[$country]['regions'] ?? []);

            if (!in_array($region, $regions, true)) {
                $validator->errors()->add('persona_bolge', 'Secilen bolge bu ulke icin gecersiz.');

                return;
            }
        }

        if ($country && $region && $city) {
            $cities = $catalog[$country]['regions'][$region] ?? [];

            if (!in_array($city, $cities, true)) {
                $validator->errors()->add('persona_sehir', 'Secilen sehir bu bolge icin gecersiz.');
            }
        }
    }

    private function mirrorLegacySettings(User $kullanici, AiPersonaProfile $persona, array $legacy): void
    {
        $engineConfig = $this->engineConfigService->activeConfig();

        AiAyar::query()->updateOrCreate(
            ['user_id' => $kullanici->id],
            [
                'aktif_mi' => $legacy['aktif_mi'],
                'saglayici_tipi' => 'gemini',
                'model_adi' => $legacy['model_adi'],
                'kisilik_aciklamasi' => $legacy['persona_ozeti'],
                'konusma_tonu' => $legacy['konusma_tonu'],
                'konusma_stili' => $legacy['konusma_stili'],
                'emoji_seviyesi' => $legacy['emoji_seviyesi'],
                'flort_seviyesi' => $legacy['flort_seviyesi'],
                'giriskenlik_seviyesi' => $legacy['giriskenlik_seviyesi'],
                'utangaclik_seviyesi' => $legacy['utangaclik_seviyesi'],
                'duygusallik_seviyesi' => $legacy['duygusallik_seviyesi'],
                'kiskanclik_seviyesi' => $legacy['kiskanclik_seviyesi'],
                'mizah_seviyesi' => $legacy['mizah_seviyesi'],
                'zeka_seviyesi' => $legacy['zeka_seviyesi'],
                'ilk_mesaj_atar_mi' => $legacy['ilk_mesaj_atar_mi'],
                'ilk_mesaj_sablonu' => $legacy['ilk_mesaj_tonu'],
                'minimum_cevap_suresi_saniye' => $legacy['minimum_cevap_suresi_saniye'],
                'maksimum_cevap_suresi_saniye' => $legacy['maksimum_cevap_suresi_saniye'],
                'mesaj_uzunlugu_min' => $legacy['mesaj_uzunlugu_min'],
                'mesaj_uzunlugu_max' => $legacy['mesaj_uzunlugu_max'],
                'saat_dilimi' => $legacy['saat_dilimi'],
                'uyku_baslangic' => $legacy['uyku_baslangic'],
                'uyku_bitis' => $legacy['uyku_bitis'],
                'hafta_sonu_uyku_baslangic' => $legacy['hafta_sonu_uyku_baslangic'],
                'hafta_sonu_uyku_bitis' => $legacy['hafta_sonu_uyku_bitis'],
                'temperature' => $engineConfig->temperature,
                'top_p' => $engineConfig->top_p,
                'max_output_tokens' => $engineConfig->max_output_tokens,
            ],
        );

        $metadata = $persona->metadata ?? [];
        $metadata['legacy_ai_ayar_sync'] = now()->toIso8601String();
        $persona->forceFill(['metadata' => $metadata])->save();
    }

    private function replaceRules(
        AiPersonaProfile $aiPersonaProfile,
        array $blockedTopics,
        array $requiredRules,
    ): void {
        AiGuardrailRule::query()
            ->where('ai_persona_profile_id', $aiPersonaProfile->id)
            ->whereIn('rule_type', ['blocked_topic', 'required_rule'])
            ->delete();

        foreach ($blockedTopics as $topic) {
            AiGuardrailRule::query()->create([
                'ai_persona_profile_id' => $aiPersonaProfile->id,
                'rule_type' => 'blocked_topic',
                'etiket' => 'Panel Yasakli Konu',
                'icerik' => $topic,
                'severity' => 'block',
                'aktif_mi' => true,
            ]);
        }

        foreach ($requiredRules as $rule) {
            AiGuardrailRule::query()->create([
                'ai_persona_profile_id' => $aiPersonaProfile->id,
                'rule_type' => 'required_rule',
                'etiket' => 'Panel Zorunlu Kural',
                'icerik' => $rule,
                'severity' => 'enforce',
                'aktif_mi' => true,
            ]);
        }
    }

    private function rulesToText(AiPersonaProfile $owner, string $ruleType): string
    {
        return AiGuardrailRule::query()
            ->where('ai_persona_profile_id', $owner->id)
            ->where('rule_type', $ruleType)
            ->pluck('icerik')
            ->implode("\n");
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
        ];
    }

    private function dropdowns(): array
    {
        return config('ai_studio_dropdowns', []);
    }

    private function textToLines(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
    }

    private function booleanInput(array $input, string $field, bool $default): bool
    {
        if (!array_key_exists($field, $input)) {
            return $default;
        }

        $value = filter_var($input[$field], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $value ?? $default;
    }
}
