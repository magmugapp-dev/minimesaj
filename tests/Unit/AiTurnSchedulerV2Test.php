<?php

use App\Models\AiPersonaProfile;
use App\Models\User;
use App\Services\YapayZeka\V2\AiTurnScheduler;
use App\Services\YapayZeka\V2\Data\AiInterpretation;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('pushes the reply out of the configured sleep window', function () {
    $aiUser = User::factory()->aiKullanici()->create();
    $hedefUser = User::factory()->create();

    $persona = new AiPersonaProfile([
        'minimum_cevap_suresi_saniye' => 5,
        'maksimum_cevap_suresi_saniye' => 5,
        'saat_dilimi' => 'Europe/Istanbul',
        'uyku_baslangic' => '23:00',
        'uyku_bitis' => '07:00',
        'hafta_sonu_uyku_baslangic' => '23:00',
        'hafta_sonu_uyku_bitis' => '08:00',
    ]);

    $referenceNow = Carbon::parse('2026-04-23 23:30:00', 'Europe/Istanbul');
    Carbon::setTestNow($referenceNow);

    $context = new AiTurnContext(
        kanal: 'dating',
        turnType: 'reply',
        aiUser: $aiUser,
        hedefUser: $hedefUser,
    );

    $interpretation = new AiInterpretation(
        intent: 'casual_chat',
        emotion: 'neutral',
        energy: 'medium',
        riskLevel: 'low',
        expectation: 'keep_flow',
        topics: ['genel'],
        summary: 'Gece mesaji',
    );

    $scheduled = app(AiTurnScheduler::class)->schedule(
        $context,
        $persona,
        $interpretation,
    );

    $plannedLocal = $scheduled['planned_at']->copy()->setTimezone('Europe/Istanbul');

    expect($plannedLocal->format('H:i'))->toBe('07:00')
        ->and($scheduled['status_text'])->toBeNull();

    Carbon::setTestNow();
});

it('scales simulated typing delay with reply length', function () {
    $scheduler = app(AiTurnScheduler::class);

    $shortDelay = $scheduler->typingDelaySeconds('Selam!');
    $longDelay = $scheduler->typingDelaySeconds(
        'Selam, nasilsin? Bugun biraz yogundum ama senin mesajini gorunce '
        . 'cevap vermek istedim. Aksam bir kahve icmek guzel olabilir.',
    );

    expect($shortDelay)->toBeGreaterThanOrEqual(2)
        ->toBeLessThanOrEqual(12)
        ->and($longDelay)->toBeGreaterThanOrEqual($shortDelay)
        ->toBeLessThanOrEqual(12);
});
