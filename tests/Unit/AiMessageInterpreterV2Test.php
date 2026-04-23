<?php

use App\Models\User;
use App\Services\YapayZeka\V2\AiMessageInterpreter;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('detects off platform push as a high risk intent', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();

    $context = new AiTurnContext(
        kanal: 'dating',
        turnType: 'reply',
        aiUser: $aiUser,
        hedefUser: $hedefUser,
    );

    $result = app(AiMessageInterpreter::class)->interpret(
        'Whatsapp verir misin, numarani yazsana?',
        $context,
    );

    expect($result->intent)->toBe('off_platform_push')
        ->and($result->riskLevel)->toBe('high')
        ->and($result->summary)->toContain('Whatsapp');
});

it('detects flirt tone and matching expectation', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();

    $context = new AiTurnContext(
        kanal: 'dating',
        turnType: 'reply',
        aiUser: $aiUser,
        hedefUser: $hedefUser,
    );

    $result = app(AiMessageInterpreter::class)->interpret(
        'Tatlisin bu arada, seni ozledim :)',
        $context,
    );

    expect($result->intent)->toBe('flirt')
        ->and($result->emotion)->toBe('flirty')
        ->and($result->expectation)->toBe('playful_reciprocity');
});
