<?php

namespace App\Services\Users;

use App\Models\AiAyar;
use App\Models\User;
use App\Models\UserAvailabilitySchedule;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Traversable;

class UserOnlineStatusService
{
    public function resolve(
        User $user,
        ?CarbonInterface $at = null,
        bool $withNextActiveAt = true,
    ): array {
        $reference = $at ? Carbon::instance($at) : now();
        $context = $this->context($user);
        $state = $this->resolveFromContext($context, $reference);

        $state['timezone'] = $context['timezone'];
        $state['local_now'] = Carbon::instance($reference)->setTimezone($context['timezone']);
        $state['next_active_at'] = $withNextActiveAt && !$state['is_online']
            ? $this->nextActiveAtFromContext($context, $reference)
            : null;

        return $state;
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
        if ($user->hesap_tipi !== 'ai' && !$user->relationLoaded('availabilitySchedules')) {
            return;
        }

        $online = (bool) ($state['is_online'] ?? false);

        if ((bool) $user->cevrim_ici_mi === $online) {
            return;
        }

        $payload = [
            'cevrim_ici_mi' => $online,
        ];

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

    private function context(User $user): array
    {
        $aiAyar = $user->relationLoaded('aiAyar')
            ? $user->aiAyar
            : $user->aiAyar()->first();

        $schedules = $user->relationLoaded('availabilitySchedules')
            ? $user->availabilitySchedules
            : $user->availabilitySchedules()->get();

        return [
            'user' => $user,
            'ai_ayar' => $aiAyar,
            'schedules' => $schedules instanceof Collection ? $schedules : collect($schedules),
            'timezone' => $this->resolveTimezone($aiAyar),
        ];
    }

    private function resolveFromContext(array $context, CarbonInterface $reference): array
    {
        /** @var User $user */
        $user = $context['user'];
        /** @var AiAyar|null $aiAyar */
        $aiAyar = $context['ai_ayar'];
        /** @var Collection<int, UserAvailabilitySchedule> $schedules */
        $schedules = $context['schedules'];
        $timezone = $context['timezone'];

        if ($user->hesap_durumu !== 'aktif') {
            return $this->statusPayload(false, 'default', false);
        }

        $defaultState = $this->defaultState($user, $aiAyar, $reference, $timezone);
        $localNow = Carbon::instance($reference)->setTimezone($timezone);
        $matchingSchedules = $this->matchingSchedules($schedules, $localNow);

        if ($matchingSchedules->contains(fn (UserAvailabilitySchedule $schedule) => $schedule->status === 'passive')) {
            return $this->statusPayload(false, 'passive_schedule', false);
        }

        if ($matchingSchedules->contains(fn (UserAvailabilitySchedule $schedule) => $schedule->status === 'active')
            && $this->canScheduleActivate($user, $aiAyar)) {
            return $this->statusPayload(true, 'active_schedule', true);
        }

        return $defaultState;
    }

    private function defaultState(
        User $user,
        ?AiAyar $aiAyar,
        CarbonInterface $reference,
        string $timezone,
    ): array {
        if ($user->hesap_tipi !== 'ai') {
            return $this->statusPayload((bool) $user->cevrim_ici_mi, 'default', (bool) $user->cevrim_ici_mi);
        }

        if (!$aiAyar) {
            return $this->statusPayload((bool) $user->cevrim_ici_mi, 'default', (bool) $user->cevrim_ici_mi);
        }

        if (!$aiAyar->aktif_mi) {
            return $this->statusPayload(false, 'default', false);
        }

        $sleepWindow = $this->currentSleepWindow($aiAyar, $reference, $timezone);

        return $this->statusPayload($sleepWindow === null, 'default', $sleepWindow === null);
    }

    private function statusPayload(bool $isOnline, string $reason, bool $activeTime): array
    {
        return [
            'is_online' => $isOnline,
            'reason' => $reason,
            'active_time' => $activeTime,
        ];
    }

    private function matchingSchedules(Collection $schedules, CarbonInterface $localNow): Collection
    {
        return $schedules->filter(function (UserAvailabilitySchedule $schedule) use ($localNow): bool {
            if ($schedule->recurrence_type === 'date') {
                if (!$schedule->specific_date || $schedule->specific_date->toDateString() !== $localNow->toDateString()) {
                    return false;
                }
            } elseif ($schedule->recurrence_type === 'weekly') {
                if ($schedule->day_of_week === null || (int) $schedule->day_of_week !== (int) $localNow->dayOfWeekIso) {
                    return false;
                }
            } else {
                return false;
            }

            if (!$schedule->starts_at || !$schedule->ends_at) {
                return false;
            }

            $start = $localNow->copy()->setTimeFromTimeString($schedule->starts_at);
            $end = $localNow->copy()->setTimeFromTimeString($schedule->ends_at);

            return $localNow->greaterThanOrEqualTo($start) && $localNow->lessThan($end);
        })->values();
    }

    private function canScheduleActivate(User $user, ?AiAyar $aiAyar): bool
    {
        if ($user->hesap_tipi !== 'ai') {
            return true;
        }

        return (bool) $aiAyar?->aktif_mi;
    }

    private function currentSleepWindow(
        AiAyar $ayar,
        CarbonInterface $reference,
        string $timezone,
    ): ?array {
        $localNow = Carbon::instance($reference)->setTimezone($timezone);

        foreach ([$localNow->copy()->subDay(), $localNow->copy()] as $day) {
            [$sleepStart, $sleepEnd] = $this->sleepHoursFor($ayar, $day);

            if (!$sleepStart || !$sleepEnd) {
                continue;
            }

            $start = $day->copy()->setTimeFromTimeString($sleepStart);
            $end = $day->copy()->setTimeFromTimeString($sleepEnd);

            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }

            if ($localNow->greaterThanOrEqualTo($start) && $localNow->lessThan($end)) {
                return [
                    'baslangic' => $start->copy()->setTimezone(config('app.timezone')),
                    'bitis' => $end->copy()->setTimezone(config('app.timezone')),
                ];
            }
        }

        return null;
    }

    private function sleepHoursFor(AiAyar $ayar, CarbonInterface $day): array
    {
        $isWeekend = $day->isWeekend();
        $start = $isWeekend
            ? ($ayar->hafta_sonu_uyku_baslangic ?: $ayar->uyku_baslangic)
            : $ayar->uyku_baslangic;
        $end = $isWeekend
            ? ($ayar->hafta_sonu_uyku_bitis ?: $ayar->uyku_bitis)
            : $ayar->uyku_bitis;

        return [$start, $end];
    }

    private function nextActiveAtFromContext(array $context, CarbonInterface $reference): ?Carbon
    {
        /** @var User $user */
        $user = $context['user'];
        /** @var AiAyar|null $aiAyar */
        $aiAyar = $context['ai_ayar'];
        /** @var Collection<int, UserAvailabilitySchedule> $schedules */
        $schedules = $context['schedules'];
        $timezone = $context['timezone'];

        $candidates = collect();

        if ($user->hesap_tipi === 'ai' && $aiAyar?->aktif_mi) {
            $sleepWindow = $this->currentSleepWindow($aiAyar, $reference, $timezone);

            if ($sleepWindow !== null) {
                $candidates->push(Carbon::instance($sleepWindow['bitis']));
            }
        }

        $localNow = Carbon::instance($reference)->setTimezone($timezone)->startOfDay();

        foreach ($schedules as $schedule) {
            if ($schedule->recurrence_type === 'date' && $schedule->specific_date) {
                $day = Carbon::createFromFormat(
                    'Y-m-d',
                    $schedule->specific_date->toDateString(),
                    $timezone,
                )->startOfDay();
                $candidates->push($day->copy()->setTimeFromTimeString($schedule->starts_at)->setTimezone(config('app.timezone')));
                $candidates->push($day->copy()->setTimeFromTimeString($schedule->ends_at)->setTimezone(config('app.timezone')));
                continue;
            }

            if ($schedule->recurrence_type === 'weekly' && $schedule->day_of_week !== null) {
                foreach (range(0, 35) as $offset) {
                    $day = $localNow->copy()->addDays($offset);

                    if ((int) $day->dayOfWeekIso !== (int) $schedule->day_of_week) {
                        continue;
                    }

                    $candidates->push($day->copy()->setTimeFromTimeString($schedule->starts_at)->setTimezone(config('app.timezone')));
                    $candidates->push($day->copy()->setTimeFromTimeString($schedule->ends_at)->setTimezone(config('app.timezone')));
                }
            }
        }

        $sortedCandidates = $candidates
            ->filter(fn ($candidate) => $candidate instanceof CarbonInterface && Carbon::instance($candidate)->greaterThan($reference))
            ->map(fn (CarbonInterface $candidate) => Carbon::instance($candidate))
            ->unique(fn (Carbon $candidate) => $candidate->toIso8601String())
            ->sortBy(fn (Carbon $candidate) => $candidate->getTimestamp())
            ->values();

        foreach ($sortedCandidates as $candidate) {
            $state = $this->resolveFromContext($context, $candidate);

            if ($state['is_online']) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveTimezone(?AiAyar $aiAyar): string
    {
        $timezone = $aiAyar?->saat_dilimi ?: config('app.timezone');

        try {
            return (new DateTimeZone($timezone))->getName();
        } catch (\Throwable) {
            return config('app.timezone');
        }
    }
}
