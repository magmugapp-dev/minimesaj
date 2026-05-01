<?php

namespace App\Events;

use App\Models\Sohbet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MesajlarOkundu implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Sohbet $sohbet,
        public int $okuyanUserId,
        public int $guncellenenSayisi,
    ) {}

    public function broadcastOn(): array
    {
        $eslesme = $this->sohbet->eslesme ?? $this->sohbet->loadMissing('eslesme')->eslesme;

        return [
            new PrivateChannel("sohbet.{$this->sohbet->id}"),
            ...$this->userChannels($eslesme?->user_id, $eslesme?->eslesen_user_id),
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

    private function userChannels(mixed ...$userIds): array
    {
        return collect($userIds)
            ->filter()
            ->unique()
            ->map(fn ($userId) => new PrivateChannel("kullanici.{$userId}"))
            ->values()
            ->all();
    }
}
