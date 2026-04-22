<?php

use App\Models\PushDeviceToken;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication to delete the current account', function () {
    $this->deleteJson('/api/auth/hesap')->assertUnauthorized();
});

it('deletes the authenticated account and cascades related records', function () {
    $kullanici = User::factory()->create();

    PushDeviceToken::query()->create([
        'user_id' => $kullanici->id,
        'token' => 'hesap-silme-token',
        'platform' => 'android',
        'bildirim_izni' => true,
    ]);

    Sanctum::actingAs($kullanici);

    $this->deleteJson('/api/auth/hesap')
        ->assertOk()
        ->assertJsonPath('mesaj', 'Hesap silindi.');

    $this->assertDatabaseMissing('users', [
        'id' => $kullanici->id,
    ]);

    $this->assertDatabaseMissing('push_device_tokens', [
        'user_id' => $kullanici->id,
        'token' => 'hesap-silme-token',
    ]);
});
