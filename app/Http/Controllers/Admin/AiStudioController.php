<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConversationState;
use App\Models\AiEngineConfig;
use App\Models\AiGuardrailRule;
use App\Models\AiMemory;
use App\Models\AiPersonaProfile;
use App\Models\AiTurnLog;
use App\Models\User;
use App\Services\YapayZeka\V2\AiEngineConfigService;
use App\Services\YapayZeka\V2\AiPersonaService;
use App\Support\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiStudioController extends Controller
{
    public function __construct(
        private ?AiEngineConfigService $engineConfigService = null,
        private ?AiPersonaService $personaService = null,
    ) {
        $this->engineConfigService ??= app(AiEngineConfigService::class);
        $this->personaService ??= app(AiPersonaService::class);
    }

    public function index(): View
    {
        $config = $this->engineConfigService->activeConfig();
        $personalar = User::query()
            ->where('hesap_tipi', 'ai')
            ->with(['aiPersonaProfile.engineConfig'])
            ->orderBy('ad')
            ->get()
            ->map(function (User $user) {
                $user->setRelation('aiPersonaProfile', $this->personaService->ensureForUser($user));

                return $user;
            });

        $istatistikler = [
            'persona_sayisi' => $personalar->count(),
            'aktif_persona' => $personalar->filter(fn (User $user) => $user->aiPersonaProfile?->aktif_mi)->count(),
            'aktif_state' => AiConversationState::query()->whereIn('ai_durumu', ['typing', 'queued'])->count(),
            'bugunku_turn' => AiTurnLog::query()->whereDate('created_at', today())->count(),
        ];

        $sonTraceler = AiTurnLog::query()
            ->with('aiUser:id,ad,soyad')
            ->latest()
            ->limit(15)
            ->get();

        return view('admin.ai-v2.index', [
            'config' => $config,
            'personalar' => $personalar,
            'istatistikler' => $istatistikler,
            'blockedTopicsText' => $this->rulesToText($config, 'blocked_topic'),
            'requiredRulesText' => $this->rulesToText($config, 'required_rule'),
            'sonTraceler' => $sonTraceler,
        ]);
    }

    public function engineUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'aktif_mi' => 'nullable|boolean',
            'model_adi' => 'required|string|max:100',
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

        return view('admin.ai-v2.show', [
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
        ]);
    }

    public function edit(User $kullanici): View
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $persona = $this->personaService->ensureForUser($kullanici);

        return view('admin.ai-v2.edit', [
            'kullanici' => $kullanici,
            'persona' => $persona,
            'blockedTopicsText' => $this->rulesToText($persona, 'blocked_topic'),
            'requiredRulesText' => $this->rulesToText($persona, 'required_rule'),
        ]);
    }

    public function update(Request $request, User $kullanici): RedirectResponse
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $persona = $this->personaService->ensureForUser($kullanici);

        $validated = $request->validate([
            'aktif_mi' => 'nullable|boolean',
            'dating_aktif_mi' => 'nullable|boolean',
            'instagram_aktif_mi' => 'nullable|boolean',
            'ilk_mesaj_atar_mi' => 'nullable|boolean',
            'ilk_mesaj_tonu' => 'nullable|string|max:500',
            'persona_ozeti' => 'nullable|string|max:2000',
            'ana_dil_kodu' => 'nullable|string|max:12',
            'ana_dil_adi' => 'nullable|string|max:80',
            'ikinci_diller' => 'nullable|string|max:800',
            'persona_ulke' => 'nullable|string|max:120',
            'persona_bolge' => 'nullable|string|max:120',
            'persona_sehir' => 'nullable|string|max:120',
            'persona_mahalle' => 'nullable|string|max:120',
            'kulturel_koken' => 'nullable|string|max:160',
            'uyruk' => 'nullable|string|max:120',
            'yasam_tarzi' => 'nullable|string|max:160',
            'meslek' => 'nullable|string|max:160',
            'sektor' => 'nullable|string|max:160',
            'egitim' => 'nullable|string|max:160',
            'okul_bolum' => 'nullable|string|max:220',
            'yas_araligi' => 'nullable|string|max:40',
            'gunluk_rutin' => 'nullable|string|max:1500',
            'hobiler' => 'nullable|string|max:1500',
            'sevdigi_mekanlar' => 'nullable|string|max:1500',
            'aile_arkadas_notu' => 'nullable|string|max:1500',
            'iliski_gecmisi_tonu' => 'nullable|string|max:180',
            'konusma_imzasi' => 'nullable|string|max:1500',
            'argo_seviyesi' => 'required|integer|min:0|max:10',
            'cevap_ritmi' => 'nullable|string|max:120',
            'emoji_aliskanligi' => 'nullable|string|max:160',
            'kacinilacak_persona_detaylari' => 'nullable|string|max:1500',
            'konusma_tonu' => 'nullable|string|max:100',
            'konusma_stili' => 'nullable|string|max:100',
            'mizah_seviyesi' => 'required|integer|min:0|max:10',
            'flort_seviyesi' => 'required|integer|min:0|max:10',
            'emoji_seviyesi' => 'required|integer|min:0|max:10',
            'giriskenlik_seviyesi' => 'required|integer|min:0|max:10',
            'utangaclik_seviyesi' => 'required|integer|min:0|max:10',
            'duygusallik_seviyesi' => 'required|integer|min:0|max:10',
            'mesaj_uzunlugu_min' => 'required|integer|min:8|max:400',
            'mesaj_uzunlugu_max' => 'required|integer|min:20|max:800',
            'minimum_cevap_suresi_saniye' => 'required|integer|min:0|max:600',
            'maksimum_cevap_suresi_saniye' => 'required|integer|min:0|max:1200',
            'saat_dilimi' => 'nullable|string|max:100',
            'uyku_baslangic' => 'nullable|string|max:5',
            'uyku_bitis' => 'nullable|string|max:5',
            'hafta_sonu_uyku_baslangic' => 'nullable|string|max:5',
            'hafta_sonu_uyku_bitis' => 'nullable|string|max:5',
            'blocked_topics' => 'nullable|string|max:4000',
            'required_rules' => 'nullable|string|max:4000',
        ]);

        $validated['aktif_mi'] = $request->boolean('aktif_mi');
        $validated['dating_aktif_mi'] = $request->boolean('dating_aktif_mi', true);
        $validated['instagram_aktif_mi'] = $request->boolean('instagram_aktif_mi', true);
        $validated['ilk_mesaj_atar_mi'] = $request->boolean('ilk_mesaj_atar_mi', true);
        $validated['ana_dil_kodu'] = Language::normalizeCode($validated['ana_dil_kodu'] ?? null);
        if (!$validated['ana_dil_kodu']) {
            $validated['ana_dil_kodu'] = 'tr';
        }
        $validated['ana_dil_adi'] = trim((string) ($validated['ana_dil_adi'] ?? ''))
            ?: Language::name($validated['ana_dil_kodu']);
        $validated['ikinci_diller'] = $this->textToLines(str_replace(',', "\n", (string) ($validated['ikinci_diller'] ?? '')));
        $validated['maksimum_cevap_suresi_saniye'] = max(
            (int) $validated['minimum_cevap_suresi_saniye'],
            (int) $validated['maksimum_cevap_suresi_saniye'],
        );
        $validated['mesaj_uzunlugu_max'] = max(
            (int) $validated['mesaj_uzunlugu_min'],
            (int) $validated['mesaj_uzunlugu_max'],
        );

        $persona->update(collect($validated)->except(['blocked_topics', 'required_rules'])->all());

        $this->replaceRules(
            aiEngineConfig: null,
            aiPersonaProfile: $persona,
            blockedTopics: $this->textToLines($validated['blocked_topics'] ?? null),
            requiredRules: $this->textToLines($validated['required_rules'] ?? null),
        );

        return redirect()
            ->route('admin.ai.goster', $kullanici)
            ->with('basari', 'Persona override ayarlari guncellendi.');
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

    private function replaceRules(
        ?AiEngineConfig $aiEngineConfig,
        ?AiPersonaProfile $aiPersonaProfile,
        array $blockedTopics,
        array $requiredRules,
    ): void {
        AiGuardrailRule::query()
            ->when($aiEngineConfig, fn ($query) => $query->where('ai_engine_config_id', $aiEngineConfig->id))
            ->when($aiPersonaProfile, fn ($query) => $query->where('ai_persona_profile_id', $aiPersonaProfile->id))
            ->when(!$aiEngineConfig && !$aiPersonaProfile, fn ($query) => $query->whereRaw('1 = 0'))
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
