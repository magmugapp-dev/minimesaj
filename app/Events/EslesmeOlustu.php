<?php

namespace App\Events;

use App\Models\Eslesme;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EslesmeOlustu implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Eslesme $eslesme) {}

    /**
     * Her iki kullanıcıya da bildirim gönder.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("kullanici.{$this->eslesme->user_id}"),
            new PrivateChannel("kullanici.{$this->eslesme->eslesen_user_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'eslesme_id' => $this->eslesme->id,
            'user_id' => $this->eslesme->user_id,
            'eslesen_user_id' => $this->eslesme->eslesen_user_id,
            'durum' => $this->eslesme->durum,
        ];
    }

    public function broadcastAs(): string
    {
        return 'eslesme.olustu';
    }
}
