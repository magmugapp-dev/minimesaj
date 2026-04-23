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
        "Bursa'da yaşıyorum.",
        $interpreter->interpret("Bursa'da yaşıyorum.", $context),
        $state,
    );

    $result = $service->analyzeIncoming(
        $context,
        "Mardin'de yaşıyorum.",
        $interpreter->interpret("Mardin'de yaşıyorum.", $context),
        $state,
    );

    expect($result['contradictions'])->not->toBeEmpty()
        ->and($result['contradictions'][0]['key'])->toBe('location_city')
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

    $service->analyzeIncoming($context, 'Hemşireyim.', $interpreter->interpret('Hemşireyim.', $context), $state);
    $jobResult = $service->analyzeIncoming($context, 'Avukatım.', $interpreter->interpret('Avukatım.', $context), $state);

    $service->analyzeIncoming($context, 'Bugün çok yorgunum.', $interpreter->interpret('Bugün çok yorgunum.', $context), $state);
    $moodResult = $service->analyzeIncoming($context, 'Bugün iyiyim.', $interpreter->interpret('Bugün iyiyim.', $context), $state);

    expect($jobResult['contradictions'])->not->toBeEmpty()
        ->and($jobResult['contradictions'][0]['key'])->toBe('job_current')
        ->and($jobResult['contradictions'][0]['should_surface'])->toBeTrue()
        ->and($moodResult['contradictions'])->toBeEmpty();
});
