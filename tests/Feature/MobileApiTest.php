<?php

use App\Events\MesajGonderildi;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\MobileUpload;
use App\Models\Sohbet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Notification::fake();
});

function mobileApiConversation(User $user, ?User $peer = null): Sohbet
{
    $peer ??= User::factory()->create();

    $match = Eslesme::query()->create([
        'user_id' => $user->id,
        'eslesen_user_id' => $peer->id,
        'durum' => 'aktif',
        'eslesme_turu' => 'rastgele',
        'eslesme_kaynagi' => 'gercek_kullanici',
    ]);

    return Sohbet::query()->create([
        'eslesme_id' => $match->id,
        'durum' => 'aktif',
    ]);
}

it('returns mobile config with cache headers and etag support', function () {
    $response = $this->getJson('/api/mobile/config');

    $response->assertOk()
        ->assertJsonPath('config_ttl_seconds', 86400)
        ->assertJsonStructure([
            'server_time',
            'public_settings' => ['uygulama_adi', 'reklamlar'],
        ]);

    $etag = $response->headers->get('ETag');
    expect($etag)->not->toBeEmpty();

    $this->withHeader('If-None-Match', $etag)
        ->getJson('/api/mobile/config')
        ->assertStatus(304);
});

it('bootstraps only the authenticated users mobile data', function () {
    $user = User::factory()->create();
    $peer = User::factory()->create();
    $other = User::factory()->create();
    $conversation = mobileApiConversation($user, $peer);
    mobileApiConversation($other, $peer);

    Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $peer->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'selam',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $conversation->update(['son_mesaj_tarihi' => now()]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/mobile/bootstrap');

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $conversation->id)
        ->assertJsonPath('notifications.unread_count', 0);
});

it('syncs only the authenticated users delta data', function () {
    $user = User::factory()->create();
    $peer = User::factory()->create();
    $other = User::factory()->create();
    $otherPeer = User::factory()->create();
    $conversation = mobileApiConversation($user, $peer);
    $otherConversation = mobileApiConversation($other, $otherPeer);
    $since = now()->subMinutes(5);

    $visibleMessage = Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $peer->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'kullanici mesaji',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Mesaj::query()->create([
        'sohbet_id' => $otherConversation->id,
        'gonderen_user_id' => $otherPeer->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'baska kullanici mesaji',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/mobile/sync', [
        'sync_token' => $since->toISOString(),
    ]);

    $response->assertOk()
        ->assertJsonPath('has_more', false)
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $conversation->id)
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.id', $visibleMessage->id);
});

it('continues mobile sync with an opaque cursor when message delta exceeds the page limit', function () {
    $user = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = mobileApiConversation($user, $peer);
    $since = now()->subHour();
    $base = now()->subMinutes(20);

    $messages = collect(range(1, 205))->map(function (int $index) use ($conversation, $peer, $base) {
        return Mesaj::query()->create([
            'sohbet_id' => $conversation->id,
            'gonderen_user_id' => $peer->id,
            'mesaj_tipi' => 'metin',
            'mesaj_metni' => "delta {$index}",
            'created_at' => $base->copy()->addSeconds($index),
            'updated_at' => $base->copy()->addSeconds($index),
        ]);
    })->values();
    $conversation->update([
        'son_mesaj_id' => $messages->last()->id,
        'son_mesaj_tarihi' => $messages->last()->created_at,
    ]);

    Sanctum::actingAs($user);

    $first = $this->postJson('/api/mobile/sync', [
        'sync_token' => $since->toISOString(),
    ]);

    $first->assertOk()
        ->assertJsonPath('has_more', true)
        ->assertJsonCount(200, 'messages')
        ->assertJsonPath('messages.0.id', $messages[0]->id)
        ->assertJsonPath('messages.199.id', $messages[199]->id);

    $cursor = $first->json('sync_token');
    expect($cursor)->toBeString()
        ->and(str_starts_with($cursor, 'mobile-sync:v1:'))->toBeTrue();

    $second = $this->postJson('/api/mobile/sync', [
        'sync_token' => $cursor,
    ]);

    $second->assertOk()
        ->assertJsonPath('has_more', false)
        ->assertJsonCount(5, 'messages')
        ->assertJsonPath('messages.0.id', $messages[200]->id)
        ->assertJsonPath('messages.4.id', $messages[204]->id);
});

it('paginates conversation messages with before and after cursors', function () {
    $user = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = mobileApiConversation($user, $peer);
    $messages = collect(range(1, 5))->map(function (int $index) use ($conversation, $peer) {
        return Mesaj::query()->create([
            'sohbet_id' => $conversation->id,
            'gonderen_user_id' => $peer->id,
            'mesaj_tipi' => 'metin',
            'mesaj_metni' => "mesaj {$index}",
            'created_at' => now()->addSeconds($index),
            'updated_at' => now()->addSeconds($index),
        ]);
    })->values();

    Sanctum::actingAs($user);

    $latest = $this->getJson("/api/mobile/conversations/{$conversation->id}/messages?limit=2");
    $latest->assertOk()
        ->assertJsonPath('meta.has_more_older', true)
        ->assertJsonPath('data.0.id', $messages[3]->id)
        ->assertJsonPath('data.1.id', $messages[4]->id);

    $older = $this->getJson("/api/mobile/conversations/{$conversation->id}/messages?before_id={$messages[3]->id}&limit=2");
    $older->assertOk()
        ->assertJsonPath('data.0.id', $messages[1]->id)
        ->assertJsonPath('data.1.id', $messages[2]->id);

    $newer = $this->getJson("/api/mobile/conversations/{$conversation->id}/messages?after_id={$messages[2]->id}&limit=10");
    $newer->assertOk()
        ->assertJsonPath('meta.has_more_older', false)
        ->assertJsonPath('data.0.id', $messages[3]->id)
        ->assertJsonPath('data.1.id', $messages[4]->id);
});

it('sends mobile messages idempotently with client_message_id', function () {
    $user = User::factory()->create();
    $peer = User::factory()->create();
    $conversation = mobileApiConversation($user, $peer);
    Sanctum::actingAs($user);

    $payload = [
        'client_message_id' => 'client-message-1',
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Merhaba',
    ];

    $this->postJson("/api/mobile/conversations/{$conversation->id}/messages", $payload)
        ->assertCreated()
        ->assertJsonPath('data.client_message_id', 'client-message-1');

    $this->postJson("/api/mobile/conversations/{$conversation->id}/messages", $payload)
        ->assertOk()
        ->assertJsonPath('data.client_message_id', 'client-message-1');

    expect(Mesaj::query()
        ->where('sohbet_id', $conversation->id)
        ->where('client_message_id', 'client-message-1')
        ->count())->toBe(1);
});

it('broadcasts full mobile message payloads for realtime cache patches', function () {
    Storage::fake('public');
    Storage::disk('public')->put('mesajlar/voice.m4a', 'voice');
    $user = User::factory()->create(['ad' => 'Ada', 'kullanici_adi' => 'ada']);
    $peer = User::factory()->create();
    $conversation = mobileApiConversation($user, $peer);

    $message = Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $user->id,
        'mesaj_tipi' => 'ses',
        'mesaj_metni' => null,
        'dosya_yolu' => 'mesajlar/voice.m4a',
        'dosya_suresi' => 12,
        'client_message_id' => 'client-voice-1',
        'okundu_mu' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = (new MesajGonderildi($message))->broadcastWith();

    expect($payload)
        ->toHaveKey('gonderen')
        ->and($payload['id'])->toBe($message->id)
        ->and($payload['sohbet_id'])->toBe($conversation->id)
        ->and($payload['gonderen_user_id'])->toBe($user->id)
        ->and($payload['gonderen']['id'])->toBe($user->id)
        ->and($payload['mesaj_tipi'])->toBe('ses')
        ->and(str_contains($payload['dosya_yolu'], 'mesajlar/voice.m4a'))->toBeTrue()
        ->and($payload['dosya_suresi'])->toBe(12)
        ->and($payload['client_message_id'])->toBe('client-voice-1')
        ->and($payload['okundu_mu'])->toBeFalse();
});

it('does not expose another users conversation messages', function () {
    $user = User::factory()->create();
    $peer = User::factory()->create();
    $other = User::factory()->create();
    $conversation = mobileApiConversation($user, $peer);
    Sanctum::actingAs($other);

    $this->getJson("/api/mobile/conversations/{$conversation->id}/messages")
        ->assertForbidden();
});

it('deduplicates mobile uploads by client_upload_id', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $first = $this->post('/api/mobile/uploads', [
        'client_upload_id' => 'upload-1',
        'mesaj_tipi' => 'foto',
        'dosya' => UploadedFile::fake()->image('first.jpg')->size(256),
    ], ['Accept' => 'application/json']);

    $first->assertCreated()
        ->assertJsonPath('client_upload_id', 'upload-1');

    $path = $first->json('dosya_yolu');

    $second = $this->post('/api/mobile/uploads', [
        'client_upload_id' => 'upload-1',
        'mesaj_tipi' => 'foto',
        'dosya' => UploadedFile::fake()->image('second.jpg')->size(256),
    ], ['Accept' => 'application/json']);

    $second->assertOk()
        ->assertJsonPath('client_upload_id', 'upload-1')
        ->assertJsonPath('dosya_yolu', $path);

    expect(MobileUpload::query()
        ->where('user_id', $user->id)
        ->where('client_upload_id', 'upload-1')
        ->count())->toBe(1);
});
