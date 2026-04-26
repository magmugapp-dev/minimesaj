<?php

namespace App\Services\Users;

use App\Models\User;
use App\Models\UserAvailabilitySchedule;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class UserAvailabilityScheduleService
{
    public function normalizeInput(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map(function ($row): array {
            if (!is_array($row)) {
                return [];
            }

            return [
                'recurrence_type' => $this->stringValue($row['recurrence_type'] ?? $row['type'] ?? 'date'),
                'specific_date' => $this->stringValue($row['specific_date'] ?? $row['date'] ?? $row['tarih'] ?? null),
                'day_of_week' => $this->stringValue($row['day_of_week'] ?? null),
                'starts_at' => $this->stringValue($row['starts_at'] ?? $row['start_time'] ?? $row['baslangic_saati'] ?? null),
                'ends_at' => $this->stringValue($row['ends_at'] ?? $row['end_time'] ?? $row['bitis_saati'] ?? null),
                'status' => $this->normalizeStatus($row['status'] ?? $row['durum'] ?? null),
            ];
        }, $rows));
    }

    public function validateRows(Validator $validator, array $rows, string $timezone): void
    {
        $preparedRows = $this->preparedRows($rows);
        $today = $this->todayInTimezone($timezone);
        $maxDate = $today->copy()->addMonthNoOverflow();
        $validRows = [];

        foreach ($preparedRows as $row) {
            $index = $row['index'];
            $fieldPrefix = "availability_schedules.{$index}";

            if (!$row['has_values']) {
                continue;
            }

            $rowIsValid = true;

            if ($row['recurrence_type'] !== 'date') {
                $validator->errors()->add("{$fieldPrefix}.recurrence_type", 'Su an yalnizca tarih bazli saat araliklari kaydedilebilir.');
                $rowIsValid = false;
            }

            if ($row['specific_date'] === '') {
                $validator->errors()->add("{$fieldPrefix}.specific_date", 'Saat araligi girdiginde tarih zorunludur.');
                $rowIsValid = false;
            }

            if ($row['starts_at'] === '') {
                $validator->errors()->add("{$fieldPrefix}.starts_at", 'Saat araligi girdiginde baslangic saati zorunludur.');
                $rowIsValid = false;
            }

            if ($row['ends_at'] === '') {
                $validator->errors()->add("{$fieldPrefix}.ends_at", 'Saat araligi girdiginde bitis saati zorunludur.');
                $rowIsValid = false;
            }

            if ($row['status'] === '') {
                $validator->errors()->add("{$fieldPrefix}.status", 'Saat araligi girdiginde durum secmelisin.');
                $rowIsValid = false;
            }

            if (!$this->isValidTime($row['starts_at'])) {
                $validator->errors()->add("{$fieldPrefix}.starts_at", 'Baslangic saati gecerli degil.');
                $rowIsValid = false;
            }

            if (!$this->isValidTime($row['ends_at'])) {
                $validator->errors()->add("{$fieldPrefix}.ends_at", 'Bitis saati gecerli degil.');
                $rowIsValid = false;
            }

            if ($row['status'] !== '' && !in_array($row['status'], ['active', 'passive'], true)) {
                $validator->errors()->add("{$fieldPrefix}.status", 'Durum aktif veya pasif olmalidir.');
                $rowIsValid = false;
            }

            $specificDate = null;
            if ($row['specific_date'] !== '') {
                try {
                    $specificDate = Carbon::createFromFormat('Y-m-d', $row['specific_date'], $this->safeTimezone($timezone))->startOfDay();
                } catch (\Throwable) {
                    $validator->errors()->add("{$fieldPrefix}.specific_date", 'Tarih gecerli degil.');
                    $rowIsValid = false;
                }
            }

            if ($specificDate && ($specificDate->lt($today) || $specificDate->gt($maxDate))) {
                $validator->errors()->add(
                    "{$fieldPrefix}.specific_date",
                    sprintf(
                        'Tarih %s ile %s arasinda olmali.',
                        $today->toDateString(),
                        $maxDate->toDateString(),
                    ),
                );
                $rowIsValid = false;
            }

            if ($this->isValidTime($row['starts_at']) && $this->isValidTime($row['ends_at'])) {
                if ($this->normalizedTime($row['starts_at']) >= $this->normalizedTime($row['ends_at'])) {
                    $validator->errors()->add("{$fieldPrefix}.starts_at", 'Baslangic saati bitis saatinden kucuk olmali.');
                    $rowIsValid = false;
                }
            }

            if ($rowIsValid) {
                $validRows[] = [
                    'index' => $index,
                    'specific_date' => $specificDate?->toDateString(),
                    'starts_at' => $this->normalizedTime($row['starts_at']),
                    'ends_at' => $this->normalizedTime($row['ends_at']),
                    'status' => $row['status'],
                ];
            }
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        collect($validRows)
            ->groupBy('specific_date')
            ->each(function (Collection $group) use ($validator): void {
                $sorted = $group->sortBy([
                    ['starts_at', 'asc'],
                    ['ends_at', 'asc'],
                ])->values();

                $previous = null;

                foreach ($sorted as $row) {
                    if ($previous && $row['starts_at'] < $previous['ends_at']) {
                        $validator->errors()->add(
                            "availability_schedules.{$row['index']}.starts_at",
                            'Ayni gun icindeki saat araliklari birbiriyle cakisamaz.',
                        );
                    }

                    $previous = $row;
                }
            });
    }

    public function sanitizedRows(array $rows): array
    {
        return collect($this->preparedRows($rows))
            ->filter(fn (array $row) => $row['has_values'])
            ->filter(fn (array $row) => $row['specific_date'] !== ''
                && $this->isValidTime($row['starts_at'])
                && $this->isValidTime($row['ends_at'])
                && in_array($row['status'], ['active', 'passive'], true))
            ->map(function (array $row): array {
                return [
                    'recurrence_type' => 'date',
                    'specific_date' => $row['specific_date'],
                    'day_of_week' => null,
                    'starts_at' => $this->normalizedTime($row['starts_at']),
                    'ends_at' => $this->normalizedTime($row['ends_at']),
                    'status' => $row['status'],
                    'metadata' => null,
                ];
            })
            ->sortBy([
                ['specific_date', 'asc'],
                ['starts_at', 'asc'],
                ['ends_at', 'asc'],
            ])
            ->values()
            ->all();
    }

    public function replaceForUser(User $user, array $rows): void
    {
        UserAvailabilitySchedule::query()
            ->where('user_id', $user->id)
            ->delete();

        if ($rows === []) {
            return;
        }

        $user->availabilitySchedules()->createMany($rows);
    }

    public function formRowsForUser(?User $user): array
    {
        if (!$user) {
            return [];
        }

        $rows = $user->relationLoaded('availabilitySchedules')
            ? $user->availabilitySchedules
            : $user->availabilitySchedules()
                ->orderBy('specific_date')
                ->orderBy('starts_at')
                ->get();

        return $rows
            ->map(function (UserAvailabilitySchedule $row): array {
                return [
                    'recurrence_type' => $row->recurrence_type,
                    'specific_date' => $row->specific_date?->toDateString(),
                    'day_of_week' => $row->day_of_week,
                    'starts_at' => $this->displayTime($row->starts_at),
                    'ends_at' => $this->displayTime($row->ends_at),
                    'status' => $row->status,
                ];
            })
            ->values()
            ->all();
    }

    private function preparedRows(array $rows): array
    {
        return collect($this->normalizeInput($rows))
            ->values()
            ->map(function (array $row, int $index): array {
                $specificDate = $this->stringValue($row['specific_date'] ?? null);
                $startsAt = $this->stringValue($row['starts_at'] ?? null);
                $endsAt = $this->stringValue($row['ends_at'] ?? null);
                $status = $this->normalizeStatus($row['status'] ?? null);
                $recurrenceType = $this->stringValue($row['recurrence_type'] ?? 'date') ?: 'date';
                $dayOfWeek = $this->stringValue($row['day_of_week'] ?? null);

                return [
                    'index' => $index,
                    'recurrence_type' => $recurrenceType,
                    'specific_date' => $specificDate,
                    'day_of_week' => $dayOfWeek,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => $status,
                    'has_values' => $specificDate !== ''
                        || $startsAt !== ''
                        || $endsAt !== '',
                ];
            })
            ->all();
    }

    private function stringValue(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeStatus(mixed $value): string
    {
        $normalized = mb_strtolower($this->stringValue($value));

        return match ($normalized) {
            'aktif', 'active' => 'active',
            'pasif', 'inactive', 'passive' => 'passive',
            default => $normalized,
        };
    }

    private function isValidTime(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) === 1;
    }

    private function normalizedTime(string $value): string
    {
        if (!$this->isValidTime($value)) {
            return $value;
        }

        return strlen($value) === 5 ? "{$value}:00" : $value;
    }

    private function displayTime(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return substr($value, 0, 5);
    }

    private function todayInTimezone(string $timezone): Carbon
    {
        return now()->setTimezone($this->safeTimezone($timezone))->startOfDay();
    }

    private function safeTimezone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (\Throwable) {
            return new DateTimeZone(config('app.timezone'));
        }
    }
}
