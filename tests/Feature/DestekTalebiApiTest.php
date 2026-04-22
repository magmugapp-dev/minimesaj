<?php

use App\Models\Ayar;
use App\Models\User;
use App\Notifications\DestekTalebiOlustu;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

it('requires authentication to submit a support request', function () {
    $this->postJson('/api/uygulama/destek-talebi', [
        'mesaj' => 'Merhaba destek ekibi.',
    ])->assertUnauthorized();
});

it('creates a support request for the authenticated user', function () {
    $kullanici = User::factory()->create();

    Sanctum::actingAs($kullanici);

    $this->postJson('/api/uygulama/destek-talebi', [
        'mesaj' => 'Uygulamada bildirimlerle ilgili yardima ihtiyacim var.',
    ])->assertCreated()
        ->assertJsonPath('durum', true)
        ->assertJsonPath('mesaj', 'Destek talebiniz alindi.');

    $this->assertDatabaseHas('destek_talepleri', [
        'user_id' => $kullanici->id,
        'durum' => 'yeni',
    ]);
});

it('sends a mail notification to the configured support address', function () {
    Notification::fake();

    Ayar::query()->updateOrCreate([
        'anahtar' => 'destek_eposta',
    ], [
        'deger' => 'destek@magmug.app',
        'grup' => 'genel',
        'tip' => 'string',
    ]);

    Ayar::query()->updateOrCreate([
        'anahtar' => 'site_adi',
    ], [
        'deger' => 'Magmug',
        'grup' => 'genel',
        'tip' => 'string',
    ]);

    $kullanici = User::factory()->create([
        'ad' => 'Deniz',
        'soyad' => 'Acar',
        'email' => 'deniz@example.com',
    ]);

    Sanctum::actingAs($kullanici);

    $this->postJson('/api/uygulama/destek-talebi', [
        'mesaj' => 'Destek ekibine giden bildirim test ediliyor.',
    ])->assertCreated();

    Notification::assertSentOnDemand(
        DestekTalebiOlustu::class,
        function (DestekTalebiOlustu $notification, array $channels, object $notifiable): bool {
            return in_array('mail', $channels, true)
                && $notifiable->routes['mail'] === 'destek@magmug.app';
        },
    );
});
