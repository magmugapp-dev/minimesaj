<?php

namespace App\Services\Users;

use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Traversable;

class UserOnlineStatusService
{
    public function resolve(
        User $user,
        ?CarbonInterface $at = null,
        bool $withNextActiveAt = true,
    ): array {
        $reference = $at ? Carbon::instance($at) : now();
        $character = $user->relationLoaded('aiCharacter')
            ? $user->aiCharacter
            : ($user->hesap_tipi === 'ai' ? $user->aiCharacter()->first() : null);

        if ($user->hesap_tipi !== 'ai' || !$character) {
            return [
                'is_online' => (bool) $user->cevrim_ici_mi,
                'reason' => 'default',
                'active_time' => (bool) $user->cevrim_ici_mi,
                'timezone' => config('app.timezone'),
                'local_now' => Carbon::instance($reference),
                'next_active_at' => null,
            ];
        }

        $json = $character->character_json ?? [];
        $schedule = is_array($json) ? ($json['schedule'] ?? []) : [];
        $timezone = $this->safeTimezone((string) ($schedule['timezone'] ?? config('app.timezone')));
        $local = Carbon::instance($reference)->setTimezone($timezone);
        $active = (bool) $character->active && $user->hesap_durumu === 'aktif';
        $reason = 'default';

        if ($active && $this->insideSleepWindow($schedule, $local)) {
            $active = false;
            $reason = 'sleep';
        }

        $windowState = $this->availabilityState($schedule['availability_schedules'] ?? [], $local);
        if ($windowState === 'passive') {
            $active = false;
            $reason = 'passive_schedule';
        } elseif ($windowState === 'active' && $character->active && $user->hesap_durumu === 'aktif') {
            $active = true;
            $reason = 'active_schedule';
        }

        return [
            'is_online' => $active,
            'reason' => $reason,
            'active_time' => $active,
            'timezone' => $timezone,
            'local_now' => $local,
            'next_active_at' => $withNextActiveAt && !$active ? $this->nextActiveAt($schedule, $local) : null,
        ];
    }

    public function sync(User $user, ?CarbonInterface $at = null): array
    {
        $state = $this->resolve($user, $at, false);
        $this->syncResolvedStatus($user, $state, $at);

        return $state;
    }

    public function syncResolvedStatus(
        User $user,
        array $state,
        ?CarbonInterface $at = null,
    ): void {
        if ($user->hesap_tipi !== 'ai') {
            return;
        }

        $online = (bool) ($state['is_online'] ?? false);
        if ((bool) $user->cevrim_ici_mi === $online) {
            return;
        }

        $payload = ['cevrim_ici_mi' => $online];
        if (!$online && $user->cevrim_ici_mi) {
            $payload['son_gorulme_tarihi'] = $at ? Carbon::instance($at) : now();
        }

        $user->forceFill($payload)->save();
    }

    public function syncCollection(iterable $users, ?CarbonInterface $at = null): void
    {
        if ($users instanceof Traversable) {
            $users = collect($users);
        }

        foreach ($users as $user) {
            if ($user instanceof User) {
                $this->sync($user, $at);
            }
        }
    }

    private function insideSleepWindow(array $schedule, Carbon $local): bool
    {
        $start = $local->isWeekend()
            ? ($schedule['sleep_start_weekend'] ?? $schedule['sleep_start_weekday'] ?? null)
            : ($schedule['sleep_start_weekday'] ?? null);
        $end = $local->isWeekend()
            ? ($schedule['sleep_end_weekend'] ?? $schedule['sleep_end_weekday'] ?? null)
            : ($schedule['sleep_end_weekday'] ?? null);

        if (!$this->validTime($start) || !$this->validTime($end)) {
            return false;
        }

        foreach ([$local->copy()->subDay(), $local->copy()] as $day) {
            $sleepStart = $day->copy()->setTimeFromTimeString($start);
            $sleepEnd = $day->copy()->setTimeFromTimeString($end);
            if ($sleepEnd->lessThanOrEqualTo($sleepStart)) {
                $sleepEnd->addDay();
            }
            if ($local->greaterThanOrEqualTo($sleepStart) && $local->lessThan($sleepEnd)) {
                return true;
            }
        }

        return false;
    }

    private function availabilityState(mixed $windows, Carbon $local): ?string
    {
        if (!is_array($windows)) {
            return null;
        }

        foreach ($windows as $window) {
            if (!is_array($window) || ($window['date'] ?? null) !== $local->toDateString()) {
                continue;
            }
            $start = $window['start_time'] ?? null;
            $end = $window['end_time'] ?? null;
            if (!$this->validTime($start) || !$this->validTime($end)) {
                continue;
            }
            $rangeStart = $local->copy()->setTimeFromTimeString($start);
            $rangeEnd = $local->copy()->setTimeFromTimeString($end);
            if ($local->greaterThanOrEqualTo($rangeStart) && $local->lessThan($rangeEnd)) {
                return in_array($window['status'] ?? '', ['active', 'passive'], true)
                    ? $window['status']
                    : null;
            }
        }

        return null;
    }

    private function nextActiveAt(array $schedule, Carbon $local): ?Carbon
    {
        $windows = $schedule['availability_schedules'] ?? [];
        if (!is_array($windows)) {
            return null;
        }

        return collect($windows)
            ->filter(fn ($window) => is_array($window)
                && ($window['status'] ?? null) === 'active'
                && $this->validTime($window['start_time'] ?? null)
                && isset($window['date']))
            ->map(function (array $window) use ($local): ?Carbon {
                try {
                    return Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $window['date'].' '.substr((string) $window['start_time'], 0, 5),
                        $local->timezone,
                    )->setTimezone(config('app.timezone'));
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter(fn ($candidate) => $candidate instanceof Carbon && $candidate->greaterThan(now()))
            ->sortBy(fn (Carbon $candidate) => $candidate->getTimestamp())
            ->first();
    }

    private function validTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) === 1;
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
