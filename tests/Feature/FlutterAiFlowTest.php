<?php

use App\Models\AiBlockThreshold;
use App\Models\AiMessageTurn;
use App\Models\AiPromptVersion;
use App\Models\Ayar;
use App\Models\Engelleme;
use App\Models\Eslesme;
use App\Models\Mesaj;
use App\Models\Sohbet;
use App\Models\User;
use App\Events\AiTurnStatusUpdated;
use App\Services\MesajServisi;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('creates a pending flutter ai turn after a user message', function () {
    Notification::fake();
    Event::fake([AiTurnStatusUpdated::class]);
    [$viewer, $aiUser, $conversation] = aiConversationForTest();

    app(MesajServisi::class)->gonder($conversation, $viewer, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Merhaba',
        'client_message_id' => 'user-message-1',
    ]);

    $this->assertDatabaseHas('ai_message_turns', [
        'conversation_id' => $conversation->id,
        'ai_user_id' => $aiUser->id,
        'status' => AiMessageTurn::STATUS_PENDING,
        'attempt_count' => 0,
        'max_attempts' => 5,
    ]);
    expect($conversation->fresh()->ai_durumu)->toBe('pending');
    Event::assertDispatched(AiTurnStatusUpdated::class, function (AiTurnStatusUpdated $event) use ($conversation) {
        return $event->sohbetId === $conversation->id
            && $event->status === 'pending'
            && $event->plannedAt !== null
            && $event->turnId !== null
            && $event->aiUserId !== null
            && $event->sourceMessageId !== null;
    });
});

it('plans ai turns from source message behavior and supports pending lookahead', function () {
    Notification::fake();
    [$viewer, $aiUser, $conversation] = aiConversationForTest();

    Mesaj::query()->create([
        'sohbet_id' => $conversation->id,
        'gonderen_user_id' => $aiUser->id,
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Selam.',
        'ai_tarafindan_uretildi_mi' => true,
        'created_at' => now()->subSeconds(20),
        'updated_at' => now()->subSeconds(20),
    ]);

    $incoming = app(MesajServisi::class)->gonder($conversation, $viewer, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Merhaba',
    ]);
    $turn = AiMessageTurn::query()->where('source_message_id', $incoming->id)->firstOrFail();

    expect($turn->planned_at->betweenIncluded(
        $incoming->created_at->copy()->addSeconds(2),
        $incoming->created_at->copy()->addSeconds(5),
    ))->toBeTrue();

    Sanctum::actingAs($viewer);

    $this->getJson('/api/mobile/ai/pending-turns')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/mobile/ai/pending-turns?lookahead_seconds=120')
        ->assertOk()
        ->assertJsonPath('data.0.id', $turn->id)
        ->assertJsonPath('data.0.planned_at', $turn->planned_at->toISOString());
});

it('recovers processing ai turns that timed out', function () {
    Notification::fake();
    [$viewer, , $conversation] = aiConversationForTest();
    $incoming = app(MesajServisi::class)->gonder($conversation, $viewer, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Takilan gorev.',
    ]);
    $turn = AiMessageTurn::query()->where('source_message_id', $incoming->id)->firstOrFail();
    $turn->forceFill([
        'status' => AiMessageTurn::STATUS_PROCESSING,
        'started_at' => now()->subMinutes(5),
    ])->save();

    Artisan::call('ai:takilan-gorevleri-kurtar', ['--minutes' => 1]);

    $turn->refresh();
    expect($turn->status)->toBe(AiMessageTurn::STATUS_PENDING)
        ->and($turn->attempt_count)->toBe(1)
        ->and($turn->retry_after)->not->toBeNull();
});

it('returns pending turns and stores flutter reply parts idempotently', function () {
    Notification::fake();
    [$viewer, $aiUser, $conversation] = aiConversationForTest();

    $incoming = app(MesajServisi::class)->gonder($conversation, $viewer, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Iki baloncukla cevap ver.',
    ]);
    $turn = AiMessageTurn::query()->where('source_message_id', $incoming->id)->firstOrFail();

    Sanctum::actingAs($viewer);

    $this->getJson('/api/mobile/ai/pending-turns?lookahead_seconds=300')
        ->assertOk()
        ->assertJsonPath('data.0.id', $turn->id);

    $payload = [
        'turn_id' => $turn->id,
        'parts' => ['Ilk baloncuk', 'Ikinci baloncuk'],
        'client_message_id' => 'ai-turn-client-1',
    ];

    $this->postJson("/api/mobile/ai/conversations/{$conversation->id}/reply", $payload)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.mesaj_metni', 'Ilk baloncuk')
        ->assertJsonPath('data.1.mesaj_metni', 'Ikinci baloncuk');

    $this->postJson("/api/mobile/ai/conversations/{$conversation->id}/reply", $payload)
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(Mesaj::query()
        ->where('sohbet_id', $conversation->id)
        ->where('gonderen_user_id', $aiUser->id)
        ->count())->toBe(2);
});

it('returns auth media urls and mime metadata in ai turn context', function () {
    Storage::fake('public');
    Notification::fake();
    [$viewer, , $conversation] = aiConversationForTest();
    Storage::disk('public')->put('mesajlar/1/foto/photo.jpg', 'image-bytes');

    $incoming = app(MesajServisi::class)->gonder($conversation, $viewer, [
        'mesaj_tipi' => 'foto',
        'dosya_yolu' => 'mesajlar/1/foto/photo.jpg',
        'dosya_suresi' => null,
    ]);
    $turn = AiMessageTurn::query()->where('source_message_id', $incoming->id)->firstOrFail();

    Sanctum::actingAs($viewer);

    $this->getJson("/api/mobile/ai/conversations/{$conversation->id}/turn-context?turn_id={$turn->id}")
        ->assertOk()
        ->assertJsonPath('messages.0.file_mime', 'image/jpeg')
        ->assertJsonPath('messages.0.file_url', route('mobile.messages.media', ['message' => $incoming->id]));
});

it('requires a valid turn for gemini relay and defers retryable failures', function () {
    Notification::fake();
    [$viewer, , $conversation] = aiConversationForTest();
    Ayar::query()->create([
        'anahtar' => 'gemini_api_key',
        'deger' => 'test-key',
        'grup' => 'ai',
        'tip' => 'text',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response('temporarily unavailable', 503),
    ]);

    $incoming = app(MesajServisi::class)->gonder($conversation, $viewer, [
        'mesaj_tipi' => 'metin',
        'mesaj_metni' => 'Relay denemesi.',
    ]);
    $turn = AiMessageTurn::query()->where('source_message_id', $incoming->id)->firstOrFail();

    Sanctum::actingAs($viewer);

    $this->postJson('/api/mobile/ai/gemini/stream', [
        'model' => 'gemini-2.5-flash',
        'payload' => ['contents' => []],
    ])->assertUnprocessable();

    $this->postJson('/api/mobile/ai/gemini/stream', [
        'turn_id' => $turn->id,
        'model' => 'gemini-2.5-flash',
        'payload' => ['contents' => []],
    ])->assertStatus(503)
        ->assertJsonPath('retryable', true);

    $turn->refresh();
    expect($turn->status)->toBe(AiMessageTurn::STATUS_PENDING)
        ->and($turn->attempt_count)->toBe(1)
        ->and($turn->retry_after)->not->toBeNull();
});

it('increments violation counters and blocks when threshold is reached', function () {
    [$viewer, $aiUser] = aiConversationForTest();
    AiBlockThreshold::query()->updateOrCreate(
        ['category' => 'absolute'],
        ['threshold' => 1, 'active' => true],
    );

    Sanctum::actingAs($viewer);

    $this->postJson('/api/mobile/ai/violations', [
        'ai_user_id' => $aiUser->id,
        'category' => 'absolute',
    ])->assertOk()
        ->assertJsonPath('blocked', true);

    expect(Engelleme::query()
        ->where('engelleyen_user_id', $aiUser->id)
        ->where('engellenen_user_id', $viewer->id)
        ->exists())->toBeTrue();
});

it('imports ai characters from zip and skips existing character ids', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $existing = User::factory()->aiKullanici()->create();
    createAiCharacterForTest($existing, 'existing_character');

    $zipPath = tempnam(sys_get_temp_dir(), 'ai-import-').'.zip';
    $zip = new \ZipArchive();
    $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFromString('characters.json', json_encode([
        'characters' => [
            characterJsonForTest('existing_character', 'Eski'),
            characterJsonForTest('new_character', 'Yeni'),
        ],
    ], JSON_UNESCAPED_UNICODE));
    $zip->addFromString('new_character/profile.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
    $zip->close();

    $this->actingAs($admin)
        ->post(route('admin.ai.import.store'), [
            'zip' => new UploadedFile($zipPath, 'characters.zip', 'application/zip', null, true),
        ])
        ->assertRedirect(route('admin.ai.index'))
        ->assertSessionHas('basari');

    $this->assertDatabaseHas('ai_characters', ['character_id' => 'existing_character']);
    $this->assertDatabaseHas('ai_characters', ['character_id' => 'new_character']);
    expect(User::query()->where('kullanici_adi', 'new_character')->exists())->toBeTrue();

    @unlink($zipPath);
});

function aiConversationForTest(): array
{
    $viewer = User::factory()->create(['hesap_durumu' => 'aktif', 'dil' => 'tr']);
    $aiUser = User::factory()->aiKullanici()->create(['hesap_durumu' => 'aktif', 'dil' => 'tr']);
    createAiCharacterForTest($aiUser, 'ai_turn_character');

    $match = Eslesme::query()->create([
        'user_id' => $viewer->id,
        'eslesen_user_id' => $aiUser->id,
        'eslesme_turu' => 'otomatik',
        'eslesme_kaynagi' => 'yapay_zeka',
        'durum' => 'aktif',
        'baslatan_user_id' => $viewer->id,
    ]);

    $conversation = Sohbet::query()->create([
        'eslesme_id' => $match->id,
        'durum' => 'aktif',
    ]);

    AiPromptVersion::query()->updateOrCreate(
        ['version' => 'test-v1'],
        ['hash' => hash('sha256', '<prompt />'), 'prompt_xml' => '<prompt />', 'active' => true],
    );

    return [$viewer, $aiUser, $conversation];
}

function createAiCharacterForTest(User $user, string $characterId): void
{
    $user->aiCharacter()->create([
        'character_id' => $characterId,
        'character_version' => 1,
        'schema_version' => 'bv1.0',
        'active' => true,
        'display_name' => $user->ad,
        'username' => $user->kullanici_adi,
        'primary_language_code' => 'tr',
        'primary_language_name' => 'Turkish',
        'quality_tag' => 'A',
        'character_json' => characterJsonForTest($characterId, $user->ad),
        'model_name' => 'gemini-2.5-flash',
        'temperature' => 0.8,
        'top_p' => 0.9,
        'max_output_tokens' => 1024,
    ]);
}

function characterJsonForTest(string $characterId, string $name): array
{
    return [
        'schema_version' => 'bv1.0',
        'character_id' => $characterId,
        'character_version' => 1,
        'identity' => [
            'first_name' => $name,
            'last_name' => 'AI',
            'username' => $characterId,
            'gender' => 'female',
            'birth_year' => 1998,
            'country' => 'Turkey',
            'city' => 'Istanbul',
        ],
        'languages' => [
            'primary_language_code' => 'tr',
            'primary_language_name' => 'Turkish',
        ],
        'profile' => ['tagline' => 'Test karakteri'],
        'messaging' => ['first_message_templates' => ['Selam.']],
        'rate_limits' => ['min_response_seconds' => 0, 'max_response_seconds' => 0],
        'schedule' => ['timezone' => 'Europe/Istanbul'],
        'model_config' => ['provider' => 'gemini', 'model_name' => 'gemini-2.5-flash'],
        'quality_tag' => 'A',
    ];
}
