<?php

use App\Models\AiAyar;
use App\Models\User;
use App\Services\Users\UserOnlineStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lets active schedules override the default ai sleep window', function () {
    Carbon::setTestNow('2026-04-26 10:30:00');

    $aiUser = User::factory()->aiKullanici()->create([
        'cevrim_ici_mi' => false,
    ]);

    AiAyar::query()->create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-3.1-auto-quality',
        'saat_dilimi' => 'Europe/Istanbul',
        'uyku_baslangic' => '00:00',
        'uyku_bitis' => '23:59',
    ]);

    $aiUser->availabilitySchedules()->create([
        'recurrence_type' => 'date',
        'specific_date' => '2026-04-26',
        'starts_at' => '10:00:00',
        'ends_at' => '11:00:00',
        'status' => 'active',
    ]);

    $state = app(UserOnlineStatusService::class)->resolve($aiUser->fresh(['aiAyar', 'availabilitySchedules']));

    expect($state['is_online'])->toBeTrue()
        ->and($state['reason'])->toBe('active_schedule');

    Carbon::setTestNow();
});

it('gives passive schedules priority over active schedules', function () {
    Carbon::setTestNow('2026-04-26 10:30:00');

    $aiUser = User::factory()->aiKullanici()->create([
        'cevrim_ici_mi' => true,
    ]);

    AiAyar::query()->create([
        'user_id' => $aiUser->id,
        'aktif_mi' => true,
        'saglayici_tipi' => 'gemini',
        'model_adi' => 'gemini-3.1-auto-quality',
        'saat_dilimi' => 'Europe/Istanbul',
        'uyku_baslangic' => '23:00',
        'uyku_bitis' => '07:00',
    ]);

    $aiUser->availabilitySchedules()->createMany([
        [
            'recurrence_type' => 'date',
            'specific_date' => '2026-04-26',
            'starts_at' => '10:00:00',
            'ends_at' => '11:00:00',
            'status' => 'active',
        ],
        [
            'recurrence_type' => 'date',
            'specific_date' => '2026-04-26',
            'starts_at' => '10:15:00',
            'ends_at' => '10:45:00',
            'status' => 'passive',
        ],
    ]);

    $state = app(UserOnlineStatusService::class)->resolve($aiUser->fresh(['aiAyar', 'availabilitySchedules']));

    expect($state['is_online'])->toBeFalse()
        ->and($state['reason'])->toBe('passive_schedule');

    Carbon::setTestNow();
});
