<?php

namespace App\Jobs;

use App\Events\UserOnlineStatusChanged;
use App\Models\AiMessageTurn;
use App\Models\User;
use App\Services\Users\UserOnlineStatusService;
use DateTimeZone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SetAiOffline implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $aiUserId) {}

    public function handle(): void
    {
        $aiUser = User::query()
            ->where('hesap_tipi', 'ai')
            ->find($this->aiUserId);

        if (!$aiUser) {
            return;
        }

        $aiUser->forceFill([
            'cevrim_ici_mi' => false,
            'son_gorulme_tarihi' => now(),
        ])->save();

        $nextOnlineAt = $this->nextOnlineAt($aiUser);
        AiMessageTurn::query()
            ->where('ai_user_id', $aiUser->id)
            ->whereIn('status', [AiMessageTurn::STATUS_PENDING, AiMessageTurn::STATUS_PROCESSING])
            ->update([
                'status' => AiMessageTurn::STATUS_DEFERRED,
                'planned_at' => $nextOnlineAt,
            ]);

        UserOnlineStatusChanged::dispatch($aiUser->id, false);
    }

    private function nextOnlineAt(User $aiUser): Carbon
    {
        $state = app(UserOnlineStatusService::class)->resolve($aiUser->loadMissing('aiCharacter'), now(), true);
        $nextActiveAt = $state['next_active_at'] ?? null;
        if ($nextActiveAt instanceof Carbon) {
            return $nextActiveAt;
        }

        $character = $aiUser->aiCharacter;
        $schedule = is_array($character?->character_json)
            ? ($character->character_json['schedule'] ?? [])
            : [];
        if (!is_array($schedule)) {
            return now()->addHours(3);
        }

        $timezone = $this->safeTimezone((string) ($schedule['timezone'] ?? config('app.timezone')));
        $local = now($timezone);
        $prefix = $local->isWeekend() ? 'weekend' : 'weekday';
        $end = $schedule[$prefix]['sleep_end']
            ?? $schedule["sleep_end_{$prefix}"]
            ?? $schedule["{$prefix}_sleep_end"]
            ?? $schedule['sleep_end']
            ?? null;

        if (!is_string($end) || preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $end) !== 1) {
            return now()->addHours(3);
        }

        $wakeAt = $local->copy()->setTimeFromTimeString($end);
        if ($wakeAt->lessThanOrEqualTo($local)) {
            $wakeAt->addDay();
        }

        return $wakeAt->addMinutes(random_int(5, 30))->setTimezone(config('app.timezone'));
    }

    private function safeTimezone(string $timezone): string
    {
        try {
            return (new DateTimeZone($timezone))->getName();
        } catch (\Throwable) {
            return config('app.timezone');
        }
    }
}
