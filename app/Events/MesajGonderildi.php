<?php

namespace App\Events;

use App\Models\Mesaj;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MesajGonderildi implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Mesaj $mesaj) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sohbet.{$this->mesaj->sohbet_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->mesaj->id,
            'sohbet_id' => $this->mesaj->sohbet_id,
            'gonderen_user_id' => $this->mesaj->gonderen_user_id,
            'mesaj_tipi' => $this->mesaj->mesaj_tipi,
            'mesaj_metni' => $this->mesaj->mesaj_metni,
            'cevaplanan_mesaj_id' => $this->mesaj->cevaplanan_mesaj_id,
            'created_at' => $this->mesaj->created_at->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mesaj.gonderildi';
    }
}
