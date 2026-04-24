<?php

use App\Services\YapayZeka\V2\AiJsonResponseParser;
use Tests\TestCase;

uses(TestCase::class);

it('does not fall back to raw text when structured reply parsing fails', function () {
    $parser = new AiJsonResponseParser();

    $parsed = $parser->parseReply('this is raw model output');

    expect($parsed['reply'])->toBe('')
        ->and($parsed['memory'])->toBe([]);
});

it('rescues a truncated reply field without leaking the full envelope', function () {
    $parser = new AiJsonResponseParser();

    $parsed = $parser->parseReply('{"reply":"S der');

    expect($parsed['reply'])->toBe('S der')
        ->and($parsed['memory'])->toBe([]);
});
