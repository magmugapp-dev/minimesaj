<?php

namespace App\Events;

use App\Models\Sohbet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SohbetTemizlendi implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Sohbet $sohbet) {}

    public function broadcastOn(): array
    {
        $eslesme = $this->sohbet->eslesme ?? $this->sohbet->loadMissing('eslesme')->eslesme;

        return collect([$eslesme?->user_id, $eslesme?->eslesen_user_id])
            ->filter()
            ->unique()
            ->map(fn ($userId) => new PrivateChannel("kullanici.{$userId}"))
            ->values()
            ->all();
    }

    public function broadcastWith(): array
    {
        return [
            'sohbet_id' => $this->sohbet->id,
            'temizlendi_at' => $this->sohbet->temizlendi_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sohbet.temizlendi';
    }
}
