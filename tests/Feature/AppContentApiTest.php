<?php

use App\Models\AppFaqItem;
use App\Models\AppLanguage;
use App\Models\AppLegalDocument;
use App\Models\AppTranslation;
use App\Models\AppTranslationKey;
use App\Models\User;

it('returns app content with language fallback, legal texts, faq and version metadata', function () {
    $tr = AppLanguage::query()->create([
        'code' => 'tr',
        'name' => 'Turkce',
        'native_name' => 'Turkce',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);
    $en = AppLanguage::query()->create([
        'code' => 'en',
        'name' => 'English',
        'native_name' => 'English',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 2,
    ]);

    $translationKey = AppTranslationKey::query()->create([
        'key' => 'profile.help.title',
        'default_value' => 'Yardim',
        'category' => 'profile',
        'screen' => 'profile.help',
        'is_active' => true,
    ]);
    AppTranslation::query()->create([
        'app_translation_key_id' => $translationKey->id,
        'app_language_id' => $en->id,
        'value' => 'Help',
        'is_active' => true,
    ]);

    AppLegalDocument::query()->create([
        'type' => AppLegalDocument::TYPE_PRIVACY,
        'app_language_id' => $en->id,
        'title' => 'Privacy Policy',
        'content' => 'Privacy content from panel.',
        'is_active' => true,
    ]);

    AppFaqItem::query()->create([
        'app_language_id' => $en->id,
        'question' => 'How can I get help?',
        'answer' => 'Write to support.',
        'category' => 'help',
        'screen' => 'profile.help',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/app-content?lang=tr')
        ->assertOk()
        ->assertJsonPath('defaultLanguage', 'tr')
        ->assertJsonPath('selectedLanguage', 'tr')
        ->assertJsonPath('legalTexts.privacy.title', 'Privacy Policy')
        ->assertJsonPath('legalTexts.privacy.content', 'Privacy content from panel.')
        ->assertJsonPath('faqs.0.question', 'How can I get help?')
        ->assertJsonStructure([
            'languages',
            'translations',
            'legalTexts',
            'faqs',
            'version',
            'updatedAt',
        ]);

    expect($response->json('translations')['profile.help.title'])->toBe('Help');
});

it('falls back to english when requested language is not active', function () {
    AppLanguage::query()->create([
        'code' => 'tr',
        'name' => 'Turkce',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);
    AppLanguage::query()->create([
        'code' => 'en',
        'name' => 'English',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 2,
    ]);

    $this->getJson('/api/app-content?lang=es')
        ->assertOk()
        ->assertJsonPath('selectedLanguage', 'en');
});

it('allows admins to manage languages from language text management', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $tr = AppLanguage::query()->create([
        'code' => 'tr',
        'name' => 'Turkce',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);
    $en = AppLanguage::query()->create([
        'code' => 'en',
        'name' => 'English',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 2,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dil-metin.index'))
        ->assertOk()
        ->assertSeeText('Dil ve Metin Yonetimi');

    $this->actingAs($admin)
        ->patch(route('admin.dil-metin.languages.default', $en))
        ->assertRedirect();

    expect($en->fresh()->is_default)->toBeTrue()
        ->and($tr->fresh()->is_default)->toBeFalse();
});

it('renders the language text management spa shell', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    AppLanguage::query()->create([
        'code' => 'tr',
        'name' => 'Turkce',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dil-metin.index'))
        ->assertOk()
        ->assertSeeText('SPA Workspace')
        ->assertSeeText('Keyler')
        ->assertSeeText('Diller')
        ->assertSeeText('Yasal Metinler')
        ->assertSeeText('FAQ')
        ->assertSeeText('Workspace bos');
});

it('returns json key detail with all active language translations', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $tr = AppLanguage::query()->create([
        'code' => 'tr',
        'name' => 'Turkce',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);
    $en = AppLanguage::query()->create([
        'code' => 'en',
        'name' => 'English',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 2,
    ]);
    $key = AppTranslationKey::query()->create([
        'key' => 'profile.help.title',
        'default_value' => 'Yardim',
        'category' => 'profile',
        'screen' => 'profile.help',
        'is_active' => true,
    ]);
    AppTranslation::query()->create([
        'app_translation_key_id' => $key->id,
        'app_language_id' => $tr->id,
        'value' => 'Yardim',
        'is_active' => true,
    ]);
    AppTranslation::query()->create([
        'app_translation_key_id' => $key->id,
        'app_language_id' => $en->id,
        'value' => 'Help',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.dil-metin.api.keys.show', $key->id))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.item.key', 'profile.help.title')
        ->assertJsonPath('data.translations.0.language_code', 'tr')
        ->assertJsonPath('data.translations.1.language_code', 'en')
        ->assertJsonPath('data.translations.1.value', 'Help');
});

it('filters json keys with missing translations for the selected language', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $tr = AppLanguage::query()->create([
        'code' => 'tr',
        'name' => 'Turkce',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    $complete = AppTranslationKey::query()->create([
        'key' => 'profile.help.title',
        'default_value' => 'Yardim',
        'category' => 'profile',
        'screen' => 'profile.help',
        'is_active' => true,
    ]);
    AppTranslation::query()->create([
        'app_translation_key_id' => $complete->id,
        'app_language_id' => $tr->id,
        'value' => 'Yardim',
        'is_active' => true,
    ]);

    AppTranslationKey::query()->create([
        'key' => 'profile.help.subtitle',
        'default_value' => 'Destek al',
        'category' => 'profile',
        'screen' => 'profile.help',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.dil-metin.api.keys.index', [
            'language_id' => $tr->id,
            'missing' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items.0.key', 'profile.help.subtitle');
});

it('returns standard json validation errors from admin app content endpoints', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->postJson(route('admin.dil-metin.api.keys.store'), [
            'key' => '',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['message', 'errors' => ['key']]);
});

it('archives restores and permanently deletes translation keys from app content', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $tr = AppLanguage::query()->create([
        'code' => 'tr',
        'name' => 'Turkce',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);
    $key = AppTranslationKey::query()->create([
        'key' => 'profile.help.title',
        'default_value' => 'Yardim',
        'category' => 'profile',
        'screen' => 'profile.help',
        'is_active' => true,
    ]);
    AppTranslation::query()->create([
        'app_translation_key_id' => $key->id,
        'app_language_id' => $tr->id,
        'value' => 'Yardim',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/app-content?lang=tr')
        ->assertOk();
    expect($response->json('translations')['profile.help.title'])->toBe('Yardim');

    $this->actingAs($admin)
        ->deleteJson(route('admin.dil-metin.api.keys.destroy', $key->id))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(AppTranslationKey::withTrashed()->find($key->id)?->trashed())->toBeTrue();
    expect($this->getJson('/api/app-content?lang=tr')->json('translations'))
        ->not->toHaveKey('profile.help.title');

    $this->actingAs($admin)
        ->patchJson(route('admin.dil-metin.api.keys.restore', $key->id))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(AppTranslationKey::find($key->id))->not->toBeNull();
    $response = $this->getJson('/api/app-content?lang=tr')
        ->assertOk();
    expect($response->json('translations')['profile.help.title'])->toBe('Yardim');

    $this->actingAs($admin)
        ->deleteJson(route('admin.dil-metin.api.keys.destroy', $key->id))
        ->assertOk();
    $this->actingAs($admin)
        ->deleteJson(route('admin.dil-metin.api.keys.force-destroy', $key->id))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('app_translation_keys', ['id' => $key->id]);
    $this->assertDatabaseMissing('app_translations', ['app_translation_key_id' => $key->id]);
});

it('does not archive the default language', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $tr = AppLanguage::query()->create([
        'code' => 'tr',
        'name' => 'Turkce',
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('admin.dil-metin.api.languages.destroy', $tr->id))
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(AppLanguage::withTrashed()->find($tr->id)?->trashed())->toBeFalse();
});
