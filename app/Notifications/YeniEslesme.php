<?php

namespace App\Notifications;

use App\Models\Eslesme;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Concerns\BuildsMobileNotificationPayload;
use App\Notifications\Messages\FcmMessage;
use App\Support\MediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class YeniEslesme extends Notification
{
    use Queueable;
    use BuildsMobileNotificationPayload;

    public function __construct(
        private Eslesme $eslesme,
        private User $eslesenKullanici,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationPayload($notifiable, [
            'tip' => 'yeni_eslesme',
            'eslesme_id' => $this->eslesme->id,
            'kullanici_id' => $this->eslesenKullanici->id,
            'kullanici_adi' => $this->eslesenKullanici->kullanici_adi,
            'ad' => $this->eslesenKullanici->ad,
            'profil_resmi' => MediaUrl::resolve($this->eslesenKullanici->profil_resmi),
            'baslik' => 'Yeni eslesme',
            'govde' => "{$this->eslesenKullanici->ad} ile eslestiniz!",
            'rota' => 'matches',
            'rota_parametreleri' => [
                'eslesme_id' => (string) $this->eslesme->id,
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
