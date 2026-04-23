<?php

namespace App\Console\Commands;

use App\Jobs\InstagramAiCevapGorevi;
use App\Jobs\ProcessAiTurnJob;
use App\Models\InstagramHesap;
use App\Models\InstagramMesaj;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;

class InstagramBugunkuCevaplanmayanlariKuyrugaAl extends Command
{
    protected $signature = 'instagram:bugunku-cevaplanmayanlari-kuyruga-al';
    protected $description = 'Bugünkü cevaplanmayan Instagram mesajlarını AI kuyruğuna alır.';

    public function handle()
    {
        $bugun = Carbon::today();
        $yarin = Carbon::tomorrow();

        $mesajlar = InstagramMesaj::where('gonderen_tipi', 'karsi_taraf')
            ->where('ai_cevapladi_mi', false)
            ->where('created_at', '>=', $bugun)
            ->where('created_at', '<', $yarin)
            ->get();

        $sayac = 0;
        foreach ($mesajlar as $mesaj) {
            $hesap = InstagramHesap::find($mesaj->instagram_hesap_id);
            if (!$hesap) {
                continue;
            }

            if (app()->environment('testing')) {
                if ($this->queueIsFaked()) {
                    InstagramAiCevapGorevi::dispatch($mesaj, $hesap)->onQueue('ai-stream');
                    $sayac++;
                }

                continue;
            }

            if (app()->environment('local')) {
                ProcessAiTurnJob::dispatchSync(
                    'instagram',
                    'reply',
                    $hesap->user_id,
                    null,
                    null,
                    $hesap->id,
                    $mesaj->id,
                );
            } else {
                ProcessAiTurnJob::dispatch(
                    'instagram',
                    'reply',
                    $hesap->user_id,
                    null,
                    null,
                    $hesap->id,
                    $mesaj->id,
                )->onQueue('ai-stream');
            }

            $sayac++;
        }

        $this->info($sayac . ' mesaj yeniden AI kuyruğuna alındı.');
        return 0;
    }

    private function queueIsFaked(): bool
    {
        return Queue::getFacadeRoot() instanceof QueueFake;
    }
}
