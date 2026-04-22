<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InstagramMesaj;
use App\Jobs\InstagramAiCevapGorevi;
use App\Models\InstagramHesap;
use Carbon\Carbon;

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
            if ($hesap) {
                InstagramAiCevapGorevi::dispatch($mesaj, $hesap);
                $sayac++;
            }
        }

        $this->info($sayac . ' mesaj yeniden AI kuyruğuna alındı.');
        return 0;
    }
}
