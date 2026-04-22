<?php

namespace App\Notifications\Concerns;

use App\Notifications\Messages\FcmMessage;

trait BuildsMobileNotificationPayload
{
    protected function buildNotificationPayload(object $notifiable, array $payload): array
    {
        $govde = (string) ($payload['govde'] ?? $payload['mesaj'] ?? '');
        $okunmamisSayisi = method_exists($notifiable, 'unreadNotifications')
            ? $notifiable->unreadNotifications()->count() + 1
            : null;

        return array_merge([
            'baslik' => (string) ($payload['baslik'] ?? config('app.name', 'MiniMesaj')),
            'govde' => $govde,
            'mesaj' => $govde,
            'rota' => $payload['rota'] ?? null,
            'rota_parametreleri' => $payload['rota_parametreleri'] ?? [],
            'bildirim_id' => $this->id,
            'okunmamis_sayisi' => $okunmamisSayisi,
        ], $payload);
    }

    protected function buildFcmMessage(object $notifiable, array $payload): FcmMessage
    {
        $normalized = $this->buildNotificationPayload($notifiable, $payload);
        $data = [];

        foreach ($normalized as $key => $value) {
            if ($value === null) {
                continue;
            }

            $data[$key] = is_scalar($value)
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return new FcmMessage(
            title: (string) $normalized['baslik'],
            body: (string) $normalized['govde'],
            data: $data,
            imageUrl: $normalized['profil_resmi'] ?? null,
        );
    }
}
