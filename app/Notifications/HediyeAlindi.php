<?php

namespace App\Notifications;

use App\Models\HediyeGonderimi;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Concerns\BuildsMobileNotificationPayload;
use App\Notifications\Messages\FcmMessage;
use App\Support\MediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class HediyeAlindi extends Notification
{
    use Queueable;
    use BuildsMobileNotificationPayload;

    public function __construct(
        private HediyeGonderimi $gonderim,
        private User $gonderen,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationPayload($notifiable, [
            'tip' => 'hediye_alindi',
            'gonderim_id' => $this->gonderim->id,
            'gonderen_id' => $this->gonderen->id,
            'gonderen_adi' => $this->gonderen->ad,
            'profil_resmi' => MediaUrl::resolve($this->gonderen->profil_resmi),
            'hediye_tipi' => $this->gonderim->hediye_adi,
            'puan_degeri' => $this->gonderim->puan_bedeli,
            'baslik' => 'Hediye aldin',
            'govde' => "{$this->gonderen->ad} sana bir hediye gonderdi!",
            'rota' => 'wallet',
            'rota_parametreleri' => [
                'gonderim_id' => (string) $this->gonderim->id,
            ],
        ]);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return $this->buildFcmMessage($notifiable, $this->toArray($notifiable));
    }
}
