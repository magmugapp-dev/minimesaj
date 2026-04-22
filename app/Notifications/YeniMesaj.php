<?php

namespace App\Notifications;

use App\Models\Mesaj;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Concerns\BuildsMobileNotificationPayload;
use App\Notifications\Messages\FcmMessage;
use App\Support\MediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class YeniMesaj extends Notification
{
    use Queueable;
    use BuildsMobileNotificationPayload;

    public function __construct(
        private Mesaj $mesaj,
        private User $gonderen,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationPayload($notifiable, [
            'tip' => 'yeni_mesaj',
            'mesaj_id' => $this->mesaj->id,
            'sohbet_id' => $this->mesaj->sohbet_id,
            'gonderen_id' => $this->gonderen->id,
            'gonderen_adi' => $this->gonderen->ad,
            'profil_resmi' => MediaUrl::resolve($this->gonderen->profil_resmi),
            'baslik' => $this->gonderen->ad,
            'govde' => mb_substr($this->mesaj->mesaj_metni ?? "{$this->gonderen->ad} sana yeni bir mesaj gonderdi.", 0, 120),
            'on_izleme' => mb_substr($this->mesaj->mesaj_metni ?? '', 0, 100),
            'mesaj_tipi' => $this->mesaj->mesaj_tipi,
            'rota' => 'chat',
            'rota_parametreleri' => [
                'sohbet_id' => (string) $this->mesaj->sohbet_id,
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
