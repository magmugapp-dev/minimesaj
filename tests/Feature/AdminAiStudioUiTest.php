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
        ->assertSeeText('Karakter JSON')
        ->assertSeeText('Re-engagement template JSON')
        ->assertSeeText('Profil fotografi');

    $this->actingAs($admin)
        ->get(route('admin.ai.duzenle', $character))
        ->assertOk()
        ->assertSeeText('AI karakter duzenle')
        ->assertSee('aylin_deneme');
});
