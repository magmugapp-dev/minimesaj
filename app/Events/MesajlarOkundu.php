<?php

namespace App\Events;

use App\Models\Sohbet;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MesajlarOkundu implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Sohbet $sohbet,
        public int $okuyanUserId,
        public int $guncellenenSayisi,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sohbet.{$this->sohbet->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'sohbet_id' => $this->sohbet->id,
            'okuyan_user_id' => $this->okuyanUserId,
            'guncellenen_sayisi' => $this->guncellenenSayisi,
        ];
    }

    public function broadcastAs(): string
    {
        return 'mesajlar.okundu';
    }
}
