<?php

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

