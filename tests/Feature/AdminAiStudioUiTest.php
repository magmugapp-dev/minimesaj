<?php

use App\Models\User;

it('renders the redesigned ai studio pages for an admin', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);
    $aiUser = User::factory()->aiKullanici()->create([
        'ad' => 'Aylin',
        'soyad' => 'Deneme',
    ]);
    $character = $aiUser->aiCharacter()->create([
        'character_id' => 'aylin_deneme',
        'character_version' => 1,
        'schema_version' => 'bv1.0',
        'active' => true,
        'display_name' => 'Aylin Deneme',
        'username' => $aiUser->kullanici_adi,
        'primary_language_code' => 'tr',
        'primary_language_name' => 'Turkish',
        'quality_tag' => 'A',
        'character_json' => [
            'schema_version' => 'bv1.0',
            'character_id' => 'aylin_deneme',
            'character_version' => 1,
            'identity' => ['first_name' => 'Aylin', 'last_name' => 'Deneme', 'username' => $aiUser->kullanici_adi],
            'languages' => ['primary_language_code' => 'tr', 'primary_language_name' => 'Turkish'],
            'model_config' => ['model_name' => 'gemini-2.5-flash'],
        ],
        'model_name' => 'gemini-2.5-flash',
        'temperature' => 0.8,
        'top_p' => 0.9,
        'max_output_tokens' => 1024,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.ai.index'))
        ->assertOk()
        ->assertSeeText('AI karakterler')
        ->assertSeeText('Karakter listesi')
        ->assertSeeText('Global prompt')
        ->assertSeeText('Blok esikleri')
        ->assertSeeText('Aylin Deneme')
        ->assertSeeText('gemini-2.5-flash');

    $this->actingAs($admin)
        ->get(route('admin.ai.ekle'))
        ->assertOk()
        ->assertSeeText('Yeni AI karakter')
        ->assertSeeText('Character ID')
        ->assertSeeText('Min cevap saniye')
        ->assertSeeText('Re-engagement template JSON')
        ->assertSeeText('Profil fotografi');

    $this->actingAs($admin)
        ->get(route('admin.ai.duzenle', $character))
        ->assertOk()
        ->assertSeeText('AI karakter duzenle')
        ->assertSee('aylin_deneme');
});

it('updates an ai character from form fields and keeps bulk json import available', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $aiUser = User::factory()->aiKullanici()->create([
        'ad' => 'Aylin',
        'kullanici_adi' => 'aylin_old',
    ]);
    $character = $aiUser->aiCharacter()->create([
        'character_id' => 'aylin_old',
        'character_version' => 1,
        'schema_version' => 'bv1.0',
        'active' => true,
        'display_name' => 'Aylin',
        'username' => 'aylin_old',
        'primary_language_code' => 'tr',
        'primary_language_name' => 'Turkish',
        'quality_tag' => 'A',
        'character_json' => [
            'schema_version' => 'bv1.0',
            'character_id' => 'aylin_old',
            'character_version' => 1,
            'identity' => ['first_name' => 'Aylin', 'username' => 'aylin_old', 'gender' => 'female', 'birth_year' => 1998],
            'languages' => ['primary_language_code' => 'tr', 'primary_language_name' => 'Turkish'],
            'profile' => ['tagline' => 'Eski'],
            'personality' => ['warmth' => 'warm'],
            'messaging' => ['average_message_length' => 60, 'message_length_min' => 5, 'message_length_max' => 220],
            'schedule' => ['timezone' => 'Europe/Istanbul'],
            'rate_limits' => ['daily_chat_limit' => 100, 'per_user_daily_message_limit' => 50, 'min_response_seconds' => 3, 'max_response_seconds' => 30],
            'model_config' => ['model_name' => 'gemini-2.5-flash'],
            'custom_unknown' => ['kept' => true],
        ],
        'model_name' => 'gemini-2.5-flash',
        'temperature' => 0.8,
        'top_p' => 0.9,
        'max_output_tokens' => 1024,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.ai.guncelle', $character), aiCharacterFormPayload([
            'character_id' => 'aylin_new',
            'first_name' => 'Aylin Yeni',
            'username' => 'aylin_new',
            'tagline' => 'Yeni tagline',
            'min_response_seconds' => 0,
            'max_response_seconds' => 10,
        ]))
        ->assertRedirect(route('admin.ai.duzenle', $character));

    $character->refresh();
    expect($character->character_id)->toBe('aylin_new')
        ->and(data_get($character->character_json, 'identity.first_name'))->toBe('Aylin Yeni')
        ->and(data_get($character->character_json, 'profile.tagline'))->toBe('Yeni tagline')
        ->and(data_get($character->character_json, 'rate_limits.max_response_seconds'))->toBe(10)
        ->and(data_get($character->character_json, 'custom_unknown.kept'))->toBeTrue()
        ->and($character->user->fresh()->kullanici_adi)->toBe('aylin_new');

    $this->actingAs($admin)
        ->get(route('admin.ai.json-ekle'))
        ->assertOk()
        ->assertSeeText('JSON ile toplu AI ekle');
});

function aiCharacterFormPayload(array $overrides = []): array
{
    return array_merge([
        'active' => '1',
        'character_id' => 'form_character',
        'character_version' => 1,
        'schema_version' => 'bv1.0',
        'first_name' => 'Form',
        'last_name' => 'AI',
        'username' => 'form_character',
        'gender' => 'female',
        'birth_year' => 1998,
        'country' => 'Turkey',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'primary_language_code' => 'tr',
        'primary_language_name' => 'Turkish',
        'tagline' => 'Form tagline',
        'occupation' => 'Tester',
        'hobbies' => "kahve\nmuzik",
        'warmth' => 'warm',
        'dominance' => 'balanced',
        'humor' => 'witty',
        'openness' => 'selective',
        'flirtiness' => 'mild',
        'intelligence' => 'average',
        'average_message_length' => 60,
        'message_length_min' => 5,
        'message_length_max' => 220,
        'can_send_voice' => '1',
        'can_send_photo' => '1',
        'timezone' => 'Europe/Istanbul',
        'sleep_start_weekday' => '23:30',
        'sleep_end_weekday' => '07:30',
        'sleep_start_weekend' => '00:30',
        'sleep_end_weekend' => '09:30',
        'daily_chat_limit' => 100,
        'per_user_daily_message_limit' => 50,
        'min_response_seconds' => 0,
        'max_response_seconds' => 10,
        'model_name' => 'gemini-2.5-flash',
        'temperature' => 0.8,
        'top_p' => 0.9,
        'max_output_tokens' => 1024,
        'quality_tag' => 'A',
        'reengagement_after_hours' => 168,
        'reengagement_daily_limit' => 1,
        'reengagement_templates' => '[]',
    ], $overrides);
}
