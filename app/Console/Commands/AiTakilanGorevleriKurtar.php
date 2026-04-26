<?php

namespace App\Console\Commands;

use App\Services\YapayZeka\V2\AiTurnRecoveryService;
use Illuminate\Console\Command;

class AiTakilanGorevleriKurtar extends Command
{
    protected $signature = 'ai:takilan-gorevleri-kurtar
        {--processing-minutes=10 : Processing/generated durumunda kac dakika sonra takilmis sayilacak}
        {--typing-grace-seconds=90 : Typing teslim zamani gectikten sonra beklenecek ek sure}';

    protected $description = 'Takilan AI turn/gorev kayitlarini toparlar ve sohbet runtime durumunu idle yapar.';

    public function handle(AiTurnRecoveryService $recoveryService): int
    {
        $summary = $recoveryService->recover(
            processingStaleMinutes: max(1, (int) $this->option('processing-minutes')),
            typingGraceSeconds: max(1, (int) $this->option('typing-grace-seconds')),
        );

        $this->info(json_encode($summary, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
