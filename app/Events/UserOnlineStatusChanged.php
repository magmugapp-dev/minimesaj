<?php

namespace App\Events;

use App\Models\Eslesme;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserOnlineStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public bool $isOnline,
    ) {}

    public function broadcastOn(): array
    {
        return Eslesme::query()
            ->where('durum', 'aktif')
            ->where(function ($query): void {
                $query->where('user_id', $this->userId)
                    ->orWhere('eslesen_user_id', $this->userId);
            })
            ->get(['user_id', 'eslesen_user_id'])
            ->flatMap(fn (Eslesme $match) => [
                (int) $match->user_id === $this->userId ? null : $match->user_id,
                (int) $match->eslesen_user_id === $this->userId ? null : $match->eslesen_user_id,
            ])
            ->filter()
            ->unique()
            ->map(fn ($userId) => new PrivateChannel("kullanici.{$userId}"))
            ->values()
            ->all();
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'is_online' => $this->isOnline,
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.online.status';
    }
}
