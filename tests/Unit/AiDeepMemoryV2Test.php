<?php

use App\Models\User;
use App\Services\YapayZeka\V2\AiMemoryService;
use App\Services\YapayZeka\V2\AiMessageInterpreter;
use App\Services\YapayZeka\V2\Data\AiConversationStateSnapshot;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function deepMemoryContext(User $aiUser, User $target): AiTurnContext
{
    return new AiTurnContext(
        kanal: 'dating',
        turnType: 'reply',
        aiUser: $aiUser,
        hedefUser: $target,
    );
}

function neutralStateSnapshot(): AiConversationStateSnapshot
{
    return new AiConversationStateSnapshot(
        samimiyetPuani: 0,
        ilgiPuani: 0,
        guvenPuani: 0,
        enerjiPuani: 70,
        ruhHali: 'neutral',
        gerilimSeviyesi: 0,
        sonKonu: 'genel sohbet',
        sonKullaniciDuygusu: null,
        sonAiNiyeti: null,
        sonOzet: null,
        aiDurumu: 'idle',
    );
}

it('detects city contradictions before generation context', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $target = User::factory()->create();
    $context = deepMemoryContext($aiUser, $target);
    $interpreter = app(AiMessageInterpreter::class);
    $service = app(AiMemoryService::class);
    $state = neutralStateSnapshot();

    $service->analyzeIncoming(
        $context,
        "Bursa'da yasiyorum.",
        $interpreter->interpret("Bursa'da yasiyorum.", $context),
        $state,
    );

    $result = $service->analyzeIncoming(
        $context,
        "Mardin'de yasiyorum.",
        $interpreter->interpret("Mardin'de yasiyorum.", $context),
        $state,
    );

    expect($result['contradictions'])->not->toBeEmpty()
        ->and($result['contradictions'][0]['key'])->toBe('location_city')
        ->and($result['contradictions'][0]['label'])->toBe('yasadigin sehir')
        ->and($result['contradictions'][0]['should_surface'])->toBeTrue()
        ->and($result['contradictions'][0]['previous_value'])->toContain('Bursa')
        ->and($result['contradictions'][0]['new_value'])->toContain('Mardin');
});

it('detects profession contradictions and ignores volatile mood changes', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $target = User::factory()->create();
    $context = deepMemoryContext($aiUser, $target);
    $interpreter = app(AiMessageInterpreter::class);
    $service = app(AiMemoryService::class);
    $state = neutralStateSnapshot();

    $service->analyzeIncoming($context, 'Hemsireyim.', $interpreter->interpret('Hemsireyim.', $context), $state);
    $jobResult = $service->analyzeIncoming($context, 'Avukatim.', $interpreter->interpret('Avukatim.', $context), $state);

    $service->analyzeIncoming($context, 'Bugun cok yorgunum.', $interpreter->interpret('Bugun cok yorgunum.', $context), $state);
    $moodResult = $service->analyzeIncoming($context, 'Bugun iyiyim.', $interpreter->interpret('Bugun iyiyim.', $context), $state);

    expect($jobResult['contradictions'])->not->toBeEmpty()
        ->and($jobResult['contradictions'][0]['key'])->toBe('job_current')
        ->and($jobResult['contradictions'][0]['should_surface'])->toBeTrue()
        ->and($moodResult['contradictions'])->toBeEmpty();
});

it('extracts broad stable memory for language education goals and boundaries', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $target = User::factory()->create();
    $context = deepMemoryContext($aiUser, $target);
    $interpreter = app(AiMessageInterpreter::class);
    $service = app(AiMemoryService::class);
    $state = neutralStateSnapshot();

    $result = $service->analyzeIncoming(
        $context,
        'Ana dilim Ingilizce. Universitem Bogazici University. Hedefim yurt disinda master yapmak. Gece gec yazilmasindan rahatsiz olurum.',
        $interpreter->interpret(
            'Ana dilim Ingilizce. Universitem Bogazici University. Hedefim yurt disinda master yapmak. Gece gec yazilmasindan rahatsiz olurum.',
            $context,
        ),
        $state,
    );

    $storedKeys = collect($result['stored'])
        ->map(fn (int $id) => \App\Models\AiMemory::query()->find($id)?->anahtar)
        ->filter()
        ->values()
        ->all();

    expect($storedKeys)->toContain('language_primary')
        ->and($storedKeys)->toContain('education_school')
        ->and($storedKeys)->toContain('goal_current')
        ->and($storedKeys)->toContain('boundary_current');
});
