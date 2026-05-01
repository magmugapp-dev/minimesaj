<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiTurnStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sohbetId,
        public string $status,
        public ?string $statusText = null,
        public ?string $plannedAt = null,
        public ?int $turnId = null,
        public ?int $aiUserId = null,
        public ?int $sourceMessageId = null,
        public ?string $retryAfter = null,
    ) {}

    public function broadcastOn(): array
    {
        $sohbet = \App\Models\Sohbet::query()
            ->with('eslesme:id,user_id,eslesen_user_id')
            ->find($this->sohbetId);
        $eslesme = $sohbet?->eslesme;

        return [
            new PrivateChannel("sohbet.{$this->sohbetId}"),
            ...$this->userChannels($eslesme?->user_id, $eslesme?->eslesen_user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'sohbet_id' => $this->sohbetId,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'planned_at' => $this->plannedAt,
            'turn_id' => $this->turnId,
            'ai_user_id' => $this->aiUserId,
            'source_message_id' => $this->sourceMessageId,
            'retry_after' => $this->retryAfter,
        ];
    }

    public function broadcastAs(): string
    {
        return 'ai.turn.status';
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
