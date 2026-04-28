<?php

namespace App\Console\Commands;

use App\Events\AiTurnStatusUpdated;
use App\Models\AiMessageTurn;
use Illuminate\Console\Command;

class AiTakilanGorevleriKurtar extends Command
{
    protected $signature = 'ai:takilan-gorevleri-kurtar {--minutes=2 : Processing durumunda takili sayilacak dakika}';

    protected $description = 'Takilan AI message turn kayitlarini tekrar islenebilir hale getirir.';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);
        $recovered = 0;
        $deferred = 0;

        AiMessageTurn::query()
            ->with('conversation')
            ->where('status', AiMessageTurn::STATUS_PROCESSING)
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($turns) use (&$recovered, &$deferred): void {
                foreach ($turns as $turn) {
                    $attempts = min((int) $turn->max_attempts, (int) $turn->attempt_count + 1);
                    $shouldDefer = $attempts >= (int) $turn->max_attempts;
                    $nextStatus = $shouldDefer
                        ? AiMessageTurn::STATUS_DEFERRED
                        : AiMessageTurn::STATUS_PENDING;

                    $turn->forceFill([
                        'status' => $nextStatus,
                        'attempt_count' => $attempts,
                        'retry_after' => $shouldDefer ? now()->addMinutes(5) : now(),
                        'last_error' => 'processing_timeout_recovered',
                    ])->save();

                    $conversation = $turn->conversation;
                    if ($conversation) {
                        $conversation->forceFill([
                            'ai_durumu' => $nextStatus,
                            'ai_durum_metni' => null,
                            'ai_planlanan_cevap_at' => $turn->planned_at,
                            'ai_durum_guncellendi_at' => now(),
                        ])->save();
                        AiTurnStatusUpdated::dispatch(
                            $conversation->id,
                            $nextStatus,
                            null,
                            $turn->planned_at?->toISOString(),
                        );
                    }

                    $shouldDefer ? $deferred++ : $recovered++;
                }
            });

        $this->info("Recovered {$recovered} AI turns, deferred {$deferred}.");

        return self::SUCCESS;
    }
}
