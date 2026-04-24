<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SohbetTypingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sohbetId,
        public int $userId,
        public bool $typing,
        public ?string $statusText = null,
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
            'user_id' => $this->userId,
            'typing' => $this->typing,
            'status_text' => $this->statusText,
        ];
    }

    public function broadcastAs(): string
    {
        return 'sohbet.typing';
    }
}
