<?php

namespace App\Notifications;

use App\Models\DestekTalebi;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DestekTalebiOlustu extends Notification
{
    use Queueable;

    public function __construct(
        private DestekTalebi $talep,
        private string $uygulamaAdi,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kullanici = $this->talep->user;
        $kullaniciAdi = trim(($kullanici?->ad ?? '') . ' ' . ($kullanici?->soyad ?? ''));
        $kullaniciAdi = $kullaniciAdi !== '' ? $kullaniciAdi : 'Bilinmeyen kullanici';

        return (new MailMessage)
            ->subject($this->uygulamaAdi . ' Destek Talebi #' . $this->talep->id)
            ->greeting('Yeni destek talebi olustu.')
            ->line('Talep ID: #' . $this->talep->id)
            ->line('Kullanici: ' . $kullaniciAdi)
            ->line('E-posta: ' . ($kullanici?->email ?: '—'))
            ->line('Durum: ' . $this->talep->durum)
            ->line('Mesaj:')
            ->line($this->talep->mesaj);
    }
}
