<?php

use App\Models\AiEngineConfig;
use App\Models\AiPersonaProfile;
use App\Models\User;
use App\Services\YapayZeka\GeminiSaglayici;
use App\Services\YapayZeka\V2\AiEngineConfigService;
use App\Services\YapayZeka\V2\AiGuardrailService;
use App\Services\YapayZeka\V2\AiJsonResponseParser;
use App\Services\YapayZeka\V2\AiPersonaService;
use App\Services\YapayZeka\V2\AiResponseEvaluator;
use App\Services\YapayZeka\V2\AiResponseGenerator;
use App\Services\YapayZeka\V2\AiResponsePlanner;
use App\Services\YapayZeka\V2\Channels\AiChannelAdapterInterface;
use App\Services\YapayZeka\V2\Data\AiConversationStateSnapshot;
use App\Services\YapayZeka\V2\Data\AiInterpretation;
use App\Services\YapayZeka\V2\Data\AiResponsePlan;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('plans reply tone from the expanded behavior matrix', function () {
    $persona = new AiPersonaProfile([
        'konusma_tonu' => 'dogal',
        'konusma_stili' => 'akici',
        'mizah_seviyesi' => 8,
        'flort_seviyesi' => 7,
        'emoji_seviyesi' => 5,
        'giriskenlik_seviyesi' => 8,
        'utangaclik_seviyesi' => 2,
        'duygusallik_seviyesi' => 6,
        'argo_seviyesi' => 1,
        'sicaklik_seviyesi' => 8,
        'empati_seviyesi' => 7,
        'merak_seviyesi' => 9,
        'ozguven_seviyesi' => 6,
        'sabir_seviyesi' => 7,
        'baskinlik_seviyesi' => 3,
        'sarkastiklik_seviyesi' => 2,
        'romantizm_seviyesi' => 7,
        'oyunculuk_seviyesi' => 8,
        'ciddiyet_seviyesi' => 4,
        'gizem_seviyesi' => 4,
        'hassasiyet_seviyesi' => 6,
        'enerji_seviyesi' => 7,
        'kiskanclik_seviyesi' => 2,
        'zeka_seviyesi' => 7,
        'mesaj_uzunlugu_min' => 20,
        'mesaj_uzunlugu_max' => 180,
    ]);

    $interpretation = new AiInterpretation(
        intent: 'flirt',
        emotion: 'flirty',
        energy: 'high',
        riskLevel: 'low',
        expectation: 'playful_reciprocity',
        topics: ['cekim'],
        summary: 'hafif flort',
    );

    $state = new AiConversationStateSnapshot(40, 45, 35, 75, 'curious', 0, 'muzik', 'flirty', 'playful_reciprocity', null, 'idle');

    $plan = app(AiResponsePlanner::class)->plan($interpretation, $state, $persona);

    expect($plan->tone)->toBe('playful')
        ->and($plan->askQuestion)->toBeTrue()
        ->and($plan->styleHint)->toContain('Dogal bir oyunbazlik')
        ->and($plan->maxChars)->toBeGreaterThan($plan->minChars);
});

it('adds behavior matrix context and model override to generation prompts', function () {
    $capture = (object) [];

    $gemini = new class($capture) extends GeminiSaglayici
    {
        public function __construct(public object $capture)
        {
        }

        public function tamamlaStream(array $mesajlar, array $parametreler = [], ?callable $parcaCallback = null): array
        {
            $this->capture->mesajlar = $mesajlar;
            $this->capture->parametreler = $parametreler;

            return [
                'cevap' => json_encode(['reply' => 'Merhaba, buradayim.', 'memory' => []], JSON_UNESCAPED_UNICODE),
                'giris_token' => 12,
                'cikis_token' => 8,
                'model' => $parametreler['model_adi'] ?? 'gemini-2.5-flash',
            ];
        }
    };

    $guardrails = new class extends AiGuardrailService
    {
        public function requiredInstructions(AiPersonaProfile $persona, string $kanal): array
        {
            return [];
        }
    };

    $engineService = new class extends AiEngineConfigService
    {
        public function modelParameters(AiEngineConfig $config): array
        {
            return [
                'model_adi' => $config->model_adi,
                'temperature' => (float) $config->temperature,
                'top_p' => (float) $config->top_p,
                'max_output_tokens' => (int) $config->max_output_tokens,
            ];
        }
    };

    $adapter = new class implements AiChannelAdapterInterface
    {
        public function recentMessages(AiTurnContext $context, int $limit = 12): array
        {
            return [['role' => 'user', 'content' => 'Bugun nasilsin?']];
        }

        public function counterpartProfileLines(AiTurnContext $context): array
        {
            return ['Ad: Deniz', 'Ulke: Turkiye'];
        }

        public function hasNewerIncoming(AiTurnContext $context): bool
        {
            return false;
        }

        public function persistReply(AiTurnContext $context, User $aiUser, string $replyText): mixed
        {
            return null;
        }

        public function markIncomingHandled(AiTurnContext $context): void
        {
        }
    };

    $aiUser = User::factory()->aiKullanici()->create(['dil' => 'en']);
    $hedefUser = User::factory()->create();
    $config = AiEngineConfig::query()->create([
        'ad' => 'Test Motor',
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
        'aktif_mi' => true,
        'temperature' => 0.8,
        'top_p' => 0.9,
        'max_output_tokens' => 1024,
        'guardrail_modu' => 'strict',
    ]);

    $persona = new AiPersonaProfile([
        'ana_dil_kodu' => 'en',
        'ana_dil_adi' => 'Ingilizce',
        'persona_ozeti' => 'Canli, zeki ve sicak bir karakter.',
        'konusma_tonu' => 'sicak',
        'konusma_stili' => 'akici',
        'mizah_seviyesi' => 9,
        'flort_seviyesi' => 5,
        'emoji_seviyesi' => 4,
        'giriskenlik_seviyesi' => 7,
        'utangaclik_seviyesi' => 2,
        'duygusallik_seviyesi' => 6,
        'argo_seviyesi' => 1,
        'sicaklik_seviyesi' => 8,
        'empati_seviyesi' => 8,
        'merak_seviyesi' => 8,
        'ozguven_seviyesi' => 7,
        'sabir_seviyesi' => 6,
        'baskinlik_seviyesi' => 3,
        'sarkastiklik_seviyesi' => 2,
        'romantizm_seviyesi' => 4,
        'oyunculuk_seviyesi' => 8,
        'ciddiyet_seviyesi' => 4,
        'gizem_seviyesi' => 4,
        'hassasiyet_seviyesi' => 5,
        'enerji_seviyesi' => 7,
        'kiskanclik_seviyesi' => 2,
        'zeka_seviyesi' => 8,
        'mesaj_uzunlugu_min' => 20,
        'mesaj_uzunlugu_max' => 180,
        'metadata' => ['model_adi' => 'gemini-2.5-flash'],
    ]);

    $state = new AiConversationStateSnapshot(30, 35, 25, 70, 'happy', 0, 'sinema', 'curious', 'warm_opening', null, 'idle');
    $plan = new AiResponsePlan('warm_opening', 'warm', 18, 120, true, 4, 5, 'Sicak ve akici kal.');
    $context = new AiTurnContext('dating', 'reply', $aiUser, null, null, $hedefUser);

    $generator = new AiResponseGenerator($gemini, $engineService, $guardrails, new AiJsonResponseParser());
    $result = $generator->generate($context, $adapter, $config, $persona, $state, $plan, new Collection());

    expect($capture->parametreler['model_adi'])->toBe('gemini-2.5-flash')
        ->and($capture->mesajlar[0]['content'])->toContain('Davranis matrisi')
        ->and($capture->mesajlar[0]['content'])->toContain('Mizah 9/10')
        ->and($result->promptSummary)->toContain('Davranis matrisi');
});

it('softens risky replies when harsh slider combinations spill into the text', function () {
    $persona = new AiPersonaProfile([
        'argo_seviyesi' => 9,
        'sarkastiklik_seviyesi' => 8,
        'kiskanclik_seviyesi' => 8,
        'mesaj_uzunlugu_max' => 180,
    ]);

    $plan = new AiResponsePlan('comfort', 'careful', 20, 120, false, 0, 0, 'Yumusak kal.');
    $evaluation = app(AiResponseEvaluator::class)->evaluate(
        'Ne alaka ya, beni bozma!!!',
        $persona,
        $plan,
        ['blocked' => false, 'matches' => []],
    );

    expect($evaluation['accepted'])->toBeFalse()
        ->and($evaluation['reasons'])->toContain('riskli_ton');
});

it('backfills new behavior defaults when ensuring a persona profile', function () {
    $user = User::factory()->aiKullanici()->create();
    $user->aiAyar()->create([
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-2.5-flash',
        'kiskanclik_seviyesi' => 4,
        'zeka_seviyesi' => 7,
    ]);

    $profile = app(AiPersonaService::class)->ensureForUser($user->fresh());

    expect($profile->sicaklik_seviyesi)->not()->toBeNull()
        ->and($profile->empati_seviyesi)->not()->toBeNull()
        ->and($profile->kiskanclik_seviyesi)->toBe(4)
        ->and($profile->zeka_seviyesi)->toBe(7)
        ->and(data_get($profile->metadata, 'model_adi'))->toBe('gemini-2.5-flash');
});
