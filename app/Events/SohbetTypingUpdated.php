<?php

namespace App\Events;

use App\Models\Sohbet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SohbetTypingUpdated implements ShouldBroadcast
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
        $sohbet = Sohbet::query()
            ->with('eslesme:id,user_id,eslesen_user_id')
            ->find($this->sohbetId);
        $eslesme = $sohbet?->eslesme;

        return [
            new PrivateChannel("sohbet.{$this->sohbetId}"),
            ...$this->participantChannels(
                $eslesme?->user_id,
                $eslesme?->eslesen_user_id,
            ),
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

    /**
     * @return array<int, PrivateChannel>
     */
    private function participantChannels(?int $firstUserId, ?int $secondUserId): array
    {
        return collect([$firstUserId, $secondUserId])
            ->filter()
            ->unique()
            ->map(fn (int $userId) => new PrivateChannel("kullanici.{$userId}"))
            ->values()
            ->all();
    }
}
