<?php

namespace App\Events;

use App\Models\InstagramMesaj;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstagramAiCevapHazir implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public InstagramMesaj $mesaj) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("instagram-hesap.{$this->mesaj->instagram_hesap_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        $kisi = $this->mesaj->kisi;

        return [
            'mesaj_id' => $this->mesaj->id,
            'instagram_kisi_id' => $this->mesaj->instagram_kisi_id,
            'mesaj_metni' => $this->mesaj->mesaj_metni,
            'kisi_kodu' => $kisi?->instagram_kisi_id,
            'kisi' => $kisi ? [
                'kullanici_adi' => $kisi->kullanici_adi,
                'gorunen_ad' => $kisi->gorunen_ad,
            ] : null,
        ];
    }

    public function broadcastAs(): string
    {
        return 'instagram.ai_cevap_hazir';
    }
}
