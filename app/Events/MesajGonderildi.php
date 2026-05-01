<?php

namespace App\Events;

use App\Models\Mesaj;
use App\Http\Resources\MesajResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MesajGonderildi implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Mesaj $mesaj) {}

    public function broadcastOn(): array
    {
        $eslesme = $this->mesaj->sohbet?->eslesme
            ?? $this->mesaj->sohbet()->with('eslesme')->first()?->eslesme;

        return [
            new PrivateChannel("sohbet.{$this->mesaj->sohbet_id}"),
            ...$this->userChannels($eslesme?->user_id, $eslesme?->eslesen_user_id),
        ];
    }

    public function broadcastWith(): array
    {
        $payload = (new MesajResource(
            $this->mesaj->loadMissing('gonderen:id,ad,kullanici_adi,profil_resmi,dil')
        ))->resolve(request());
        if (! empty($payload['dosya_yolu']) && trim((string) $this->mesaj->dosya_yolu) !== '') {
            $payload['dosya_yolu'] .= '?file='.str_replace('%2F', '/', rawurlencode((string) $this->mesaj->dosya_yolu));
        }

        return array_merge($payload, [
            'gonderen_user_id' => $this->mesaj->gonderen_user_id,
        ]);
    }

    public function broadcastAs(): string
    {
        return 'mesaj.gonderildi';
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
