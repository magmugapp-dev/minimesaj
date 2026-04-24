<?php

use App\Events\SohbetTypingUpdated;
use App\Models\Eslesme;
use App\Models\Sohbet;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('allows conversation participants to broadcast typing state', function () {
    $viewer = User::factory()->create(['hesap_durumu' => 'aktif']);
    $peer = User::factory()->create(['hesap_durumu' => 'aktif']);

    $match = Eslesme::query()->create([
        'user_id' => $viewer->id,
        'eslesen_user_id' => $peer->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
        'baslatan_user_id' => $viewer->id,
    ]);
    $conversation = Sohbet::query()->create([
        'eslesme_id' => $match->id,
        'durum' => 'aktif',
    ]);

    Event::fake([SohbetTypingUpdated::class]);
    Sanctum::actingAs($viewer);

    $this->patchJson("/api/dating/sohbetler/{$conversation->id}/typing", [
        'typing' => true,
    ])->assertOk()
        ->assertJsonPath('typing', true);

    Event::assertDispatched(SohbetTypingUpdated::class, function (SohbetTypingUpdated $event) use ($conversation, $viewer) {
        return $event->sohbetId === $conversation->id
            && $event->userId === $viewer->id
            && $event->typing === true
            && $event->statusText === 'Yaziyor...';
    });

    $this->patchJson("/api/dating/sohbetler/{$conversation->id}/typing", [
        'typing' => false,
    ])->assertOk()
        ->assertJsonPath('typing', false);

    Event::assertDispatched(SohbetTypingUpdated::class, function (SohbetTypingUpdated $event) use ($conversation, $viewer) {
        return $event->sohbetId === $conversation->id
            && $event->userId === $viewer->id
            && $event->typing === false
            && $event->statusText === null;
    });
});

it('rejects typing updates from users outside the conversation', function () {
    $viewer = User::factory()->create(['hesap_durumu' => 'aktif']);
    $peer = User::factory()->create(['hesap_durumu' => 'aktif']);
    $outsider = User::factory()->create(['hesap_durumu' => 'aktif']);

    $match = Eslesme::query()->create([
        'user_id' => $viewer->id,
        'eslesen_user_id' => $peer->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'gercek_kullanici',
        'durum' => 'aktif',
        'baslatan_user_id' => $viewer->id,
    ]);
    $conversation = Sohbet::query()->create([
        'eslesme_id' => $match->id,
        'durum' => 'aktif',
    ]);

    Event::fake([SohbetTypingUpdated::class]);
    Sanctum::actingAs($outsider);

    $this->patchJson("/api/dating/sohbetler/{$conversation->id}/typing", [
        'typing' => true,
    ])->assertForbidden();

    Event::assertNotDispatched(SohbetTypingUpdated::class);
});
