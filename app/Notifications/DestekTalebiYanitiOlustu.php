<?php

namespace App\Notifications;

use App\Models\DestekTalebi;
use App\Models\DestekTalebiYaniti;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DestekTalebiYanitiOlustu extends Notification
{
    use Queueable;

    public function __construct(
        private DestekTalebi $talep,
        private DestekTalebiYaniti $yanit,
        private string $uygulamaAdi,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->uygulamaAdi . ' Destek Yanitiniz #' . $this->talep->id)
            ->greeting('Destek talebinize yeni bir yanit eklendi.')
            ->line('Talep ID: #' . $this->talep->id)
            ->line('Guncel durum: ' . $this->talep->durum)
            ->line('Yanıt:')
            ->line($this->yanit->mesaj)
            ->line('Gerekirse uygulama icinden yeni bir destek talebi olusturabilirsiniz.');
    }
}
