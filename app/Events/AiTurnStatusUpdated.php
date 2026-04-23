<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiTurnStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sohbetId,
        public string $status,
        public ?string $statusText = null,
        public ?string $plannedAt = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sohbet.{$this->sohbetId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'sohbet_id' => $this->sohbetId,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'planned_at' => $this->plannedAt,
        ];
    }

    public function broadcastAs(): string
    {
        return 'ai.turn.status';
    }
}
