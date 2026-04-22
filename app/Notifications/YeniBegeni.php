<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Concerns\BuildsMobileNotificationPayload;
use App\Notifications\Messages\FcmMessage;
use App\Support\MediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class YeniBegeni extends Notification
{
    use Queueable;
    use BuildsMobileNotificationPayload;

    public function __construct(
        private User $begenen,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationPayload($notifiable, [
            'tip' => 'yeni_begeni',
            'kullanici_id' => $this->begenen->id,
            'kullanici_adi' => $this->begenen->kullanici_adi,
            'ad' => $this->begenen->ad,
            'profil_resmi' => MediaUrl::resolve($this->begenen->profil_resmi),
            'baslik' => $this->begenen->ad,
            'govde' => "{$this->begenen->ad} seni begendi!",
            'rota' => 'incoming_likes',
            'rota_parametreleri' => [
                'kullanici_id' => (string) $this->begenen->id,
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
