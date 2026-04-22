<?php

namespace App\Notifications\Channels;

use App\Notifications\Messages\FcmMessage;
use App\Services\Notifications\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(private FcmService $fcmService) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $message = $notification->toFcm($notifiable);

        if (!$message instanceof FcmMessage) {
            return;
        }

        $tokens = array_values(array_unique(array_filter(
            (array) $notifiable->routeNotificationFor('fcm', $notification),
            fn ($token) => is_string($token) && trim($token) !== '',
        )));

        if ($tokens === []) {
            return;
        }

        $this->fcmService->sendToTokens($tokens, $message);
    }
}
