<?php

namespace App\Events;

use App\Http\Resources\MesajResource;
use App\Models\Mesaj;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class YapayZekaCevabiHazir implements ShouldBroadcastNow
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

        return array_merge($payload, [
            'gonderen_user_id' => $this->mesaj->gonderen_user_id,
        ]);
    }

    public function broadcastAs(): string
    {
        return 'yapay_zeka.cevap_hazir';
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
