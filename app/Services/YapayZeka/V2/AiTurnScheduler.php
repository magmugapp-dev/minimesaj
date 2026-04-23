<?php

namespace App\Services\YapayZeka\V2;

use App\Models\AiPersonaProfile;
use App\Services\YapayZeka\V2\Data\AiInterpretation;
use App\Services\YapayZeka\V2\Data\AiTurnContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;

class AiTurnScheduler
{
    public function schedule(
        AiTurnContext $context,
        AiPersonaProfile $persona,
        AiInterpretation $interpretation,
        ?CarbonInterface $now = null,
    ): array {
        $now = $now ? Carbon::instance($now) : now();
        $reference = $this->referenceTime($context);

        $min = max(0, (int) $persona->minimum_cevap_suresi_saniye);
        $max = max($min, (int) $persona->maksimum_cevap_suresi_saniye);
        $seed = abs((int) crc32(
            implode('|', [
                $context->kanal,
                $context->turnType,
                $context->aiUser->id,
                $context->hedefId(),
                $context->gelenMesaj?->id,
                $context->instagramMesaj?->id,
            ])
        ));

        $delay = $min;
        if ($max > $min) {
            $delay += $seed % (($max - $min) + 1);
        }

        if ($context->turnType === 'first_message') {
            $delay += 6;
        }

        if ($interpretation->emotion === 'angry') {
            $delay += 10;
        } elseif ($interpretation->emotion === 'sad') {
            $delay += 4;
        } elseif ($interpretation->intent === 'question') {
            $delay = max($min, $delay - 2);
        }

        $plannedAt = Carbon::instance($reference)->addSeconds($delay);
        $sleepAdjusted = $this->respectSleepWindow($persona, $plannedAt);
        $plannedAt = $sleepAdjusted ?? $plannedAt;

        if ($plannedAt->lt($now)) {
            $plannedAt = Carbon::instance($now);
        }

        $diff = max(0, $now->diffInSeconds($plannedAt, false));

        return [
            'planned_at' => $plannedAt,
            'delay_seconds' => $delay,
            'status_text' => $diff > 12 ? 'Dusunuyor...' : 'Yaziyor...',
        ];
    }

    private function referenceTime(AiTurnContext $context): Carbon
    {
        return Carbon::instance(
            $context->gelenMesaj?->created_at
                ?? $context->instagramMesaj?->created_at
                ?? $context->sohbet?->created_at
                ?? now()
        );
    }

    private function respectSleepWindow(
        AiPersonaProfile $persona,
        CarbonInterface $plannedAt,
    ): ?Carbon {
        $timezone = $persona->saat_dilimi ?: config('app.timezone');

        try {
            $dateTimeZone = new DateTimeZone($timezone);
        } catch (\Throwable) {
            $dateTimeZone = new DateTimeZone(config('app.timezone'));
        }

        $local = Carbon::instance($plannedAt)->setTimezone($dateTimeZone);

        foreach ([$local->copy()->subDay(), $local->copy()] as $day) {
            [$sleepStart, $sleepEnd] = $this->sleepRangeForDay($persona, $day);
            if (!$sleepStart || !$sleepEnd) {
                continue;
            }

            $start = $day->copy()->setTimeFromTimeString($sleepStart);
            $end = $day->copy()->setTimeFromTimeString($sleepEnd);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }

            if ($local->greaterThanOrEqualTo($start) && $local->lessThan($end)) {
                return $end->setTimezone(config('app.timezone'));
            }
        }

        return null;
    }

    private function sleepRangeForDay(AiPersonaProfile $persona, CarbonInterface $day): array
    {
        $weekend = $day->isWeekend();

        return [
            $weekend ? ($persona->hafta_sonu_uyku_baslangic ?: $persona->uyku_baslangic) : $persona->uyku_baslangic,
            $weekend ? ($persona->hafta_sonu_uyku_bitis ?: $persona->uyku_bitis) : $persona->uyku_bitis,
        ];
    }
}
