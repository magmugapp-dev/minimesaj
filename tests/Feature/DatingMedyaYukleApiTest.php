<?php

use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('uploads photo media for chat', function () {
    Storage::fake('public');
    $kullanici = User::factory()->create();
    Sanctum::actingAs($kullanici);

    $response = $this->post('/api/dating/medya-yukle', [
        'mesaj_tipi' => 'foto',
        'dosya' => UploadedFile::fake()->image('chat_foto.jpg')->size(512),
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJsonPath('mesaj', 'Medya yuklendi.')
        ->assertJsonPath('mime_tipi', 'image/jpeg');

    $dosyaYolu = $response->json('dosya_yolu');
    expect($dosyaYolu)->toStartWith("mesajlar/{$kullanici->id}/foto/");
    Storage::disk('public')->assertExists($dosyaYolu);
});

it('uploads audio media for chat', function () {
    Storage::fake('public');
    $kullanici = User::factory()->create();
    Sanctum::actingAs($kullanici);

    $response = $this->post('/api/dating/medya-yukle', [
        'mesaj_tipi' => 'ses',
        'dosya' => UploadedFile::fake()->create('voice.m4a', 1200, 'audio/x-m4a'),
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJsonPath('mesaj', 'Medya yuklendi.');

    $dosyaYolu = $response->json('dosya_yolu');
    expect($dosyaYolu)->toStartWith("mesajlar/{$kullanici->id}/ses/");
    Storage::disk('public')->assertExists($dosyaYolu);
});

it('uploads m4a audio media reported as audio mp4 for chat', function () {
    Storage::fake('public');
    $kullanici = User::factory()->create();
    Sanctum::actingAs($kullanici);

    $response = $this->post('/api/dating/medya-yukle', [
        'mesaj_tipi' => 'ses',
        'dosya' => UploadedFile::fake()->create('voice.m4a', 1200, 'audio/mp4'),
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJsonPath('mesaj', 'Medya yuklendi.');

    $dosyaYolu = $response->json('dosya_yolu');
    expect($dosyaYolu)->toStartWith("mesajlar/{$kullanici->id}/ses/");
    Storage::disk('public')->assertExists($dosyaYolu);
});

it('returns 422 for invalid type or size limit violations', function () {
    Storage::fake('public');
    $kullanici = User::factory()->create();
    Sanctum::actingAs($kullanici);

    $this->post('/api/dating/medya-yukle', [
        'mesaj_tipi' => 'foto',
        'dosya' => UploadedFile::fake()->create('bad.txt', 5, 'text/plain'),
    ], [
        'Accept' => 'application/json',
    ])->assertStatus(422)->assertJsonValidationErrors('dosya');

    $this->post('/api/dating/medya-yukle', [
        'mesaj_tipi' => 'foto',
        'dosya' => UploadedFile::fake()->image('too_big.jpg')->size(12000),
    ], [
        'Accept' => 'application/json',
    ])->assertStatus(422)->assertJsonValidationErrors('dosya');
});

it('returns 401 when unauthenticated user uploads media', function () {
    $this->post('/api/dating/medya-yukle', [
        'mesaj_tipi' => 'foto',
        'dosya' => UploadedFile::fake()->image('unauth.jpg'),
    ], [
        'Accept' => 'application/json',
    ])->assertUnauthorized();
});

it('serves chat message media through authenticated mobile endpoint', function () {
    Storage::fake('public');
    $viewer = User::factory()->create();
    $peer = User::factory()->create();
    $stranger = User::factory()->create();
    $match = Eslesme::query()->create([
        'user_id' => $viewer->id,
        'eslesen_user_id' => $peer->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'baslatan_user_id' => $viewer->id,
    ]);
    $conversation = Sohbet::query()->create([
        'eslesme_id' => $match->id,
        'durum' => 'aktif',
    ]);
    Storage::disk('public')->put('mesajlar/1/foto/test.jpg', 'image-bytes');
    $message = Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $viewer->id,
        'mesaj_tipi' => 'foto',
        'dosya_yolu' => 'mesajlar/1/foto/test.jpg',
    ]);

    Sanctum::actingAs($viewer);
    $this->get(route('mobile.messages.media', ['message' => $message->id]))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    Sanctum::actingAs($stranger);
    $this->get(route('mobile.messages.media', ['message' => $message->id]))
        ->assertForbidden();
});
