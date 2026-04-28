<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiBlockThreshold;
use App\Models\AiCharacter;
use App\Models\AiPromptVersion;
use App\Models\User;
use App\Models\UserFotografi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ZipArchive;

class AiCharacterController extends Controller
{
    public function index()
    {
        return view('admin.ai.index', [
            'characters' => AiCharacter::query()->with('user.fotograflar')->latest()->paginate(25),
            'prompt' => AiPromptVersion::query()->where('active', true)->latest('id')->first(),
            'thresholds' => AiBlockThreshold::query()->orderBy('category')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.ai.form', [
            'character' => null,
            'json' => $this->defaultCharacterJson(),
            'formData' => $this->formDataFromJson($this->defaultCharacterJson()),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedPayload($request);
        $characterJson = $this->characterJsonFromPayload($payload);

        DB::transaction(function () use ($payload, $characterJson, $request): void {
            $user = User::query()->create([
                'ad' => data_get($characterJson, 'identity.first_name', 'AI'),
                'soyad' => data_get($characterJson, 'identity.last_name'),
                'kullanici_adi' => data_get($characterJson, 'identity.username'),
                'email' => null,
                'password' => Hash::make(Str::random(32)),
                'hesap_tipi' => 'ai',
                'hesap_durumu' => $payload['active'] ? 'aktif' : 'pasif',
                'dogum_yili' => data_get($characterJson, 'identity.birth_year'),
                'cinsiyet' => $this->genderToUserValue(data_get($characterJson, 'identity.gender')),
                'ulke' => data_get($characterJson, 'identity.country'),
                'il' => data_get($characterJson, 'identity.city'),
                'ilce' => data_get($characterJson, 'identity.district'),
                'biyografi' => data_get($characterJson, 'profile.tagline'),
                'dil' => data_get($characterJson, 'languages.primary_language_code', 'tr'),
                'cevrim_ici_mi' => $payload['active'],
            ]);

            $character = $user->aiCharacter()->create($this->characterAttributes($payload, $characterJson));
            $this->storeProfileImage($request, $user, $character);
        });

        return redirect()->route('admin.ai.index')->with('basari', 'AI karakter oluşturuldu.');
    }

    public function edit(AiCharacter $character)
    {
        return view('admin.ai.form', [
            'character' => $character->load('user.fotograflar'),
            'json' => $character->character_json,
            'formData' => $this->formDataFromJson($character->character_json, $character),
        ]);
    }

    public function update(Request $request, AiCharacter $character)
    {
        $payload = $this->validatedPayload($request, $character);
        $characterJson = $this->characterJsonFromPayload($payload, $character);

        DB::transaction(function () use ($payload, $character, $characterJson, $request): void {
            $character->user->forceFill([
                'ad' => data_get($characterJson, 'identity.first_name', $character->user->ad),
                'soyad' => data_get($characterJson, 'identity.last_name'),
                'kullanici_adi' => data_get($characterJson, 'identity.username', $character->user->kullanici_adi),
                'hesap_durumu' => $payload['active'] ? 'aktif' : 'pasif',
                'dogum_yili' => data_get($characterJson, 'identity.birth_year'),
                'cinsiyet' => $this->genderToUserValue(data_get($characterJson, 'identity.gender')),
                'ulke' => data_get($characterJson, 'identity.country'),
                'il' => data_get($characterJson, 'identity.city'),
                'ilce' => data_get($characterJson, 'identity.district'),
                'biyografi' => data_get($characterJson, 'profile.tagline'),
                'dil' => data_get($characterJson, 'languages.primary_language_code', 'tr'),
                'cevrim_ici_mi' => $payload['active'],
            ])->save();

            $character->forceFill($this->characterAttributes($payload, $characterJson))->save();
            $this->storeProfileImage($request, $character->user, $character);
        });

        return redirect()->route('admin.ai.duzenle', $character)->with('basari', 'AI karakter güncellendi.');
    }

    public function destroy(AiCharacter $character)
    {
        $character->user()->delete();

        return redirect()->route('admin.ai.index')->with('basari', 'AI karakter silindi.');
    }

    public function promptUpdate(Request $request)
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:64'],
            'prompt_xml' => ['required', 'string'],
        ]);

        AiPromptVersion::query()->update(['active' => false]);
        AiPromptVersion::query()->updateOrCreate(
            ['version' => $validated['version']],
            [
                'hash' => hash('sha256', $validated['prompt_xml']),
                'prompt_xml' => $validated['prompt_xml'],
                'active' => true,
            ],
        );

        return back()->with('basari', 'Global prompt güncellendi.');
    }

    public function thresholdsUpdate(Request $request)
    {
        $validated = $request->validate([
            'thresholds' => ['required', 'array'],
            'thresholds.*' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        foreach ($validated['thresholds'] as $category => $threshold) {
            AiBlockThreshold::query()->updateOrCreate(
                ['category' => $category],
                ['threshold' => $threshold, 'active' => true],
            );
        }

        return back()->with('basari', 'Blok eşikleri güncellendi.');
    }

    public function importForm()
    {
        return view('admin.ai.import');
    }

    public function importJsonForm()
    {
        return view('admin.ai.json-ekle', [
            'sablon' => json_encode([$this->defaultCharacterJson()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function importJsonStore(Request $request)
    {
        $validated = $request->validate([
            'json_veri' => ['required', 'string'],
        ]);

        $decoded = json_decode($validated['json_veri'], true);
        if (!is_array($decoded)) {
            return back()->withInput()->with('hata', 'JSON verisi okunamadı.');
        }

        $rows = is_array($decoded['characters'] ?? null) ? $decoded['characters'] : $decoded;
        if (isset($rows['character_id'])) {
            $rows = [$rows];
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $characterId = trim((string) ($row['character_id'] ?? ''));
            if ($characterId === '' || AiCharacter::query()->where('character_id', $characterId)->exists()) {
                $skipped++;
                continue;
            }

            try {
                DB::transaction(function () use ($row, $characterId): void {
                    $user = User::query()->create([
                        'ad' => data_get($row, 'identity.first_name', 'AI'),
                        'soyad' => data_get($row, 'identity.last_name'),
                        'kullanici_adi' => data_get($row, 'identity.username', $characterId),
                        'password' => Hash::make(Str::random(32)),
                        'hesap_tipi' => 'ai',
                        'hesap_durumu' => data_get($row, 'model_config.active', true) ? 'aktif' : 'pasif',
                        'dogum_yili' => data_get($row, 'identity.birth_year'),
                        'cinsiyet' => $this->genderToUserValue(data_get($row, 'identity.gender')),
                        'ulke' => data_get($row, 'identity.country'),
                        'il' => data_get($row, 'identity.city'),
                        'ilce' => data_get($row, 'identity.district'),
                        'biyografi' => data_get($row, 'profile.tagline'),
                        'dil' => data_get($row, 'languages.primary_language_code', 'tr'),
                        'cevrim_ici_mi' => data_get($row, 'model_config.active', true),
                    ]);

                    $user->aiCharacter()->create($this->characterAttributes([
                        'active' => (bool) data_get($row, 'model_config.active', true),
                        'model_name' => data_get($row, 'model_config.model_name', 'gemini-2.5-flash'),
                        'temperature' => data_get($row, 'model_config.temperature', 0.8),
                        'top_p' => data_get($row, 'model_config.top_p', 0.9),
                        'max_output_tokens' => data_get($row, 'model_config.max_output_tokens', 1024),
                        'reengagement_active' => false,
                        'reengagement_after_hours' => 168,
                        'reengagement_daily_limit' => 1,
                        'reengagement_templates' => [],
                    ], $row));
                });
                $created++;
            } catch (\Throwable $error) {
                $errors[] = ($index + 1).'. kayıt: '.$error->getMessage();
            }
        }

        return redirect()
            ->route('admin.ai.index')
            ->with('basari', "{$created} karakter eklendi, {$skipped} karakter atlandı.")
            ->with('hatalar', $errors);
    }

    public function importZip(Request $request)
    {
        $request->validate(['zip' => ['required', 'file', 'mimes:zip']]);

        $zip = new ZipArchive();
        $opened = $zip->open($request->file('zip')->getRealPath());
        abort_if($opened !== true, 422, 'ZIP açılamadı.');

        $jsonName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.json')) {
                $jsonName = $name;
                break;
            }
        }
        abort_unless($jsonName, 422, 'ZIP içinde JSON dosyası bulunamadı.');

        $decoded = json_decode($zip->getFromName($jsonName), true);
        $rows = is_array($decoded['characters'] ?? null) ? $decoded['characters'] : $decoded;
        abort_unless(is_array($rows), 422, 'JSON karakter listesi okunamadı.');

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $characterId = trim((string) ($row['character_id'] ?? ''));
            if ($characterId === '' || AiCharacter::query()->where('character_id', $characterId)->exists()) {
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($row, $characterId, $zip): void {
                $user = User::query()->create([
                    'ad' => data_get($row, 'identity.first_name', 'AI'),
                    'soyad' => data_get($row, 'identity.last_name'),
                    'kullanici_adi' => data_get($row, 'identity.username', $characterId),
                    'password' => Hash::make(Str::random(32)),
                    'hesap_tipi' => 'ai',
                    'hesap_durumu' => data_get($row, 'model_config.active', true) ? 'aktif' : 'pasif',
                    'dogum_yili' => data_get($row, 'identity.birth_year'),
                    'cinsiyet' => $this->genderToUserValue(data_get($row, 'identity.gender')),
                    'ulke' => data_get($row, 'identity.country'),
                    'il' => data_get($row, 'identity.city'),
                    'ilce' => data_get($row, 'identity.district'),
                    'biyografi' => data_get($row, 'profile.tagline'),
                    'dil' => data_get($row, 'languages.primary_language_code', 'tr'),
                    'cevrim_ici_mi' => data_get($row, 'model_config.active', true),
                ]);

                $character = $user->aiCharacter()->create($this->characterAttributes([
                    'active' => (bool) data_get($row, 'model_config.active', true),
                    'model_name' => data_get($row, 'model_config.model_name', 'gemini-2.5-flash'),
                    'temperature' => data_get($row, 'model_config.temperature', 0.8),
                    'top_p' => data_get($row, 'model_config.top_p', 0.9),
                    'max_output_tokens' => data_get($row, 'model_config.max_output_tokens', 1024),
                    'reengagement_active' => false,
                    'reengagement_after_hours' => 168,
                    'reengagement_daily_limit' => 1,
                    'reengagement_templates' => [],
                ], $row));

                $this->storeZipProfileImage($zip, $characterId, $user, $character);
            });
            $created++;
        }

        $zip->close();

        return redirect()->route('admin.ai.index')->with('basari', "{$created} karakter eklendi, {$skipped} karakter atlandı.");
    }

    private function validatedPayload(Request $request, ?AiCharacter $character = null): array
    {
        return $request->validate([
            'active' => ['nullable', 'boolean'],
            'character_id' => ['required', 'string', 'max:96', Rule::unique('ai_characters', 'character_id')->ignore($character?->id)],
            'character_version' => ['required', 'integer', 'min:1', 'max:9999'],
            'schema_version' => ['required', 'string', 'max:24'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'string', 'max:40'],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:'.now()->year],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'primary_language_code' => ['required', 'string', 'max:12'],
            'primary_language_name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:500'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'hobbies' => ['nullable', 'string', 'max:1000'],
            'warmth' => ['nullable', 'string', 'max:80'],
            'dominance' => ['nullable', 'string', 'max:80'],
            'humor' => ['nullable', 'string', 'max:80'],
            'openness' => ['nullable', 'string', 'max:80'],
            'flirtiness' => ['nullable', 'string', 'max:80'],
            'intelligence' => ['nullable', 'string', 'max:80'],
            'average_message_length' => ['required', 'integer', 'min:1', 'max:5000'],
            'message_length_min' => ['required', 'integer', 'min:1', 'max:5000'],
            'message_length_max' => ['required', 'integer', 'min:1', 'max:5000'],
            'can_send_voice' => ['nullable', 'boolean'],
            'can_send_photo' => ['nullable', 'boolean'],
            'timezone' => ['required', 'string', 'max:80'],
            'sleep_start_weekday' => ['nullable', 'date_format:H:i'],
            'sleep_end_weekday' => ['nullable', 'date_format:H:i'],
            'sleep_start_weekend' => ['nullable', 'date_format:H:i'],
            'sleep_end_weekend' => ['nullable', 'date_format:H:i'],
            'daily_chat_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'per_user_daily_message_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'min_response_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'max_response_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'model_name' => ['required', 'string', 'max:80'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_p' => ['required', 'numeric', 'min:0', 'max:1'],
            'max_output_tokens' => ['required', 'integer', 'min:64', 'max:8192'],
            'quality_tag' => ['nullable', 'string', 'max:8'],
            'reengagement_active' => ['nullable', 'boolean'],
            'reengagement_after_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'reengagement_daily_limit' => ['required', 'integer', 'min:0', 'max:20'],
            'reengagement_templates' => ['nullable', 'string'],
            'profile_image' => ['nullable', 'image', 'max:8192'],
        ]) + [
            'active' => $request->boolean('active'),
            'can_send_voice' => $request->boolean('can_send_voice'),
            'can_send_photo' => $request->boolean('can_send_photo'),
            'reengagement_active' => $request->boolean('reengagement_active'),
        ];
    }

    private function characterJsonFromPayload(array $payload, ?AiCharacter $character = null): array
    {
        $json = $character?->character_json ?: $this->defaultCharacterJson();
        $json = $this->mergeCharacterFormPayload($json, $payload);
        abort_unless(is_array($json), 422, 'Karakter JSON okunamadı.');
        abort_unless(trim((string) ($json['character_id'] ?? '')) !== '', 422, 'character_id zorunlu.');

        return $json;
    }

    private function mergeCharacterFormPayload(array $json, array $payload): array
    {
        data_set($json, 'schema_version', $payload['schema_version']);
        data_set($json, 'character_id', $payload['character_id']);
        data_set($json, 'character_version', (int) $payload['character_version']);
        data_set($json, 'identity.first_name', $payload['first_name']);
        data_set($json, 'identity.last_name', $payload['last_name'] ?? null);
        data_set($json, 'identity.username', $payload['username']);
        data_set($json, 'identity.gender', $payload['gender']);
        data_set($json, 'identity.birth_year', (int) $payload['birth_year']);
        data_set($json, 'identity.age', max(18, (int) now()->year - (int) $payload['birth_year']));
        data_set($json, 'identity.country', $payload['country'] ?? null);
        data_set($json, 'identity.city', $payload['city'] ?? null);
        data_set($json, 'identity.district', $payload['district'] ?? null);
        data_set($json, 'languages.primary_language_code', $payload['primary_language_code']);
        data_set($json, 'languages.primary_language_name', $payload['primary_language_name']);
        data_set($json, 'profile.tagline', $payload['tagline'] ?? null);
        data_set($json, 'profile.occupation', $payload['occupation'] ?? null);
        data_set($json, 'profile.hobbies', $this->splitLines($payload['hobbies'] ?? null));
        foreach (['warmth', 'dominance', 'humor', 'openness', 'flirtiness', 'intelligence'] as $trait) {
            data_set($json, "personality.{$trait}", $payload[$trait] ?? null);
        }
        data_set($json, 'messaging.average_message_length', (int) $payload['average_message_length']);
        data_set($json, 'messaging.message_length_min', (int) $payload['message_length_min']);
        data_set($json, 'messaging.message_length_max', (int) $payload['message_length_max']);
        data_set($json, 'messaging.can_send_voice', (bool) $payload['can_send_voice']);
        data_set($json, 'messaging.can_send_photo', (bool) $payload['can_send_photo']);
        data_set($json, 'schedule.timezone', $payload['timezone']);
        data_set($json, 'schedule.sleep_start_weekday', $payload['sleep_start_weekday'] ?? null);
        data_set($json, 'schedule.sleep_end_weekday', $payload['sleep_end_weekday'] ?? null);
        data_set($json, 'schedule.sleep_start_weekend', $payload['sleep_start_weekend'] ?? null);
        data_set($json, 'schedule.sleep_end_weekend', $payload['sleep_end_weekend'] ?? null);
        data_set($json, 'rate_limits.daily_chat_limit', (int) $payload['daily_chat_limit']);
        data_set($json, 'rate_limits.per_user_daily_message_limit', (int) $payload['per_user_daily_message_limit']);
        data_set($json, 'rate_limits.min_response_seconds', (int) $payload['min_response_seconds']);
        data_set($json, 'rate_limits.max_response_seconds', max((int) $payload['min_response_seconds'], (int) $payload['max_response_seconds']));
        data_set($json, 'model_config.active', (bool) $payload['active']);
        data_set($json, 'model_config.model_name', $payload['model_name']);
        data_set($json, 'model_config.temperature', (float) $payload['temperature']);
        data_set($json, 'model_config.top_p', (float) $payload['top_p']);
        data_set($json, 'model_config.max_output_tokens', (int) $payload['max_output_tokens']);
        data_set($json, 'quality_tag', $payload['quality_tag'] ?: 'A');

        return $json;
    }

    private function characterAttributes(array $payload, array $json): array
    {
        return [
            'character_id' => $json['character_id'],
            'character_version' => (int) ($json['character_version'] ?? 1),
            'schema_version' => $json['schema_version'] ?? 'bv1.0',
            'active' => (bool) ($payload['active'] ?? true),
            'display_name' => trim(data_get($json, 'identity.first_name', '').' '.data_get($json, 'identity.last_name', '')),
            'username' => data_get($json, 'identity.username'),
            'primary_language_code' => data_get($json, 'languages.primary_language_code', 'tr'),
            'primary_language_name' => data_get($json, 'languages.primary_language_name', 'Turkish'),
            'city' => data_get($json, 'identity.city'),
            'quality_tag' => $json['quality_tag'] ?? 'A',
            'character_json' => $json,
            'model_name' => $payload['model_name'] ?? data_get($json, 'model_config.model_name', 'gemini-2.5-flash'),
            'temperature' => (float) ($payload['temperature'] ?? 0.8),
            'top_p' => (float) ($payload['top_p'] ?? 0.9),
            'max_output_tokens' => (int) ($payload['max_output_tokens'] ?? 1024),
            'reengagement_active' => (bool) ($payload['reengagement_active'] ?? false),
            'reengagement_after_hours' => (int) ($payload['reengagement_after_hours'] ?? 168),
            'reengagement_daily_limit' => (int) ($payload['reengagement_daily_limit'] ?? 1),
            'reengagement_templates' => $this->decodeTemplates($payload['reengagement_templates'] ?? null),
        ];
    }

    private function formDataFromJson(array $json, ?AiCharacter $character = null): array
    {
        return [
            'character_id' => data_get($json, 'character_id', 'new_character'),
            'character_version' => data_get($json, 'character_version', 1),
            'schema_version' => data_get($json, 'schema_version', 'bv1.0'),
            'first_name' => data_get($json, 'identity.first_name', ''),
            'last_name' => data_get($json, 'identity.last_name', ''),
            'username' => data_get($json, 'identity.username', ''),
            'gender' => data_get($json, 'identity.gender', 'female'),
            'birth_year' => data_get($json, 'identity.birth_year', now()->year - 25),
            'country' => data_get($json, 'identity.country', 'Turkey'),
            'city' => data_get($json, 'identity.city', ''),
            'district' => data_get($json, 'identity.district', ''),
            'primary_language_code' => data_get($json, 'languages.primary_language_code', 'tr'),
            'primary_language_name' => data_get($json, 'languages.primary_language_name', 'Turkish'),
            'tagline' => data_get($json, 'profile.tagline', ''),
            'occupation' => data_get($json, 'profile.occupation', ''),
            'hobbies' => implode("\n", (array) data_get($json, 'profile.hobbies', [])),
            'warmth' => data_get($json, 'personality.warmth', 'neutral'),
            'dominance' => data_get($json, 'personality.dominance', 'balanced'),
            'humor' => data_get($json, 'personality.humor', 'witty'),
            'openness' => data_get($json, 'personality.openness', 'selective'),
            'flirtiness' => data_get($json, 'personality.flirtiness', 'mild'),
            'intelligence' => data_get($json, 'personality.intelligence', 'average'),
            'average_message_length' => data_get($json, 'messaging.average_message_length', 60),
            'message_length_min' => data_get($json, 'messaging.message_length_min', 5),
            'message_length_max' => data_get($json, 'messaging.message_length_max', 220),
            'can_send_voice' => (bool) data_get($json, 'messaging.can_send_voice', false),
            'can_send_photo' => (bool) data_get($json, 'messaging.can_send_photo', false),
            'timezone' => data_get($json, 'schedule.timezone', 'Europe/Istanbul'),
            'sleep_start_weekday' => data_get($json, 'schedule.sleep_start_weekday', '23:30'),
            'sleep_end_weekday' => data_get($json, 'schedule.sleep_end_weekday', '07:30'),
            'sleep_start_weekend' => data_get($json, 'schedule.sleep_start_weekend', '00:30'),
            'sleep_end_weekend' => data_get($json, 'schedule.sleep_end_weekend', '09:30'),
            'daily_chat_limit' => data_get($json, 'rate_limits.daily_chat_limit', 100),
            'per_user_daily_message_limit' => data_get($json, 'rate_limits.per_user_daily_message_limit', 50),
            'min_response_seconds' => data_get($json, 'rate_limits.min_response_seconds', 3),
            'max_response_seconds' => data_get($json, 'rate_limits.max_response_seconds', 30),
            'model_name' => $character?->model_name ?? data_get($json, 'model_config.model_name', 'gemini-2.5-flash'),
            'temperature' => $character?->temperature ?? data_get($json, 'model_config.temperature', 0.8),
            'top_p' => $character?->top_p ?? data_get($json, 'model_config.top_p', 0.9),
            'max_output_tokens' => $character?->max_output_tokens ?? data_get($json, 'model_config.max_output_tokens', 1024),
            'quality_tag' => data_get($json, 'quality_tag', 'A'),
            'reengagement_active' => (bool) ($character?->reengagement_active ?? false),
            'reengagement_after_hours' => $character?->reengagement_after_hours ?? 168,
            'reengagement_daily_limit' => $character?->reengagement_daily_limit ?? 1,
            'reengagement_templates' => json_encode($character?->reengagement_templates ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }

    private function splitLines(?string $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    private function decodeTemplates(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function storeProfileImage(Request $request, User $user, AiCharacter $character): void
    {
        if (!$request->hasFile('profile_image')) {
            return;
        }

        $path = $request->file('profile_image')->store("ai/{$character->character_id}", 'public');
        $user->forceFill(['profil_resmi' => $path])->save();
        UserFotografi::query()->create([
            'user_id' => $user->id,
            'dosya_yolu' => $path,
            'medya_tipi' => 'image',
            'mime_tipi' => $request->file('profile_image')->getMimeType(),
            'sira_no' => 0,
            'ana_fotograf_mi' => true,
            'aktif_mi' => true,
        ]);
    }

    private function storeZipProfileImage(ZipArchive $zip, string $characterId, User $user, AiCharacter $character): void
    {
        $imageName = "{$characterId}/profile.png";
        $contents = $zip->getFromName($imageName);
        if ($contents === false) {
            return;
        }

        $path = "ai/{$characterId}/profile.png";
        Storage::disk('public')->put($path, $contents);
        $user->forceFill(['profil_resmi' => $path])->save();
        UserFotografi::query()->create([
            'user_id' => $user->id,
            'dosya_yolu' => $path,
            'medya_tipi' => 'image',
            'mime_tipi' => 'image/png',
            'sira_no' => 0,
            'ana_fotograf_mi' => true,
            'aktif_mi' => true,
        ]);
    }

    private function genderToUserValue(?string $gender): string
    {
        return match ($gender) {
            'male', 'erkek' => 'erkek',
            'female', 'kadin' => 'kadin',
            default => 'belirtmek_istemiyorum',
        };
    }

    private function defaultCharacterJson(): array
    {
        return [
            'schema_version' => 'bv1.0',
            'character_id' => 'new_character',
            'character_version' => 1,
            'identity' => [
                'first_name' => '',
                'last_name' => '',
                'username' => '',
                'gender' => 'female',
                'orientation' => 'hetero',
                'age' => 25,
                'birth_year' => (int) now()->year - 25,
                'relationship_status' => 'single',
                'country' => 'Turkey',
                'region' => '',
                'city' => '',
                'district' => '',
            ],
            'languages' => [
                'primary_language_code' => 'tr',
                'primary_language_name' => 'Turkish',
                'output_languages' => ['tr'],
                'native_phrases' => [],
            ],
            'profile' => ['tagline' => '', 'occupation' => '', 'industry' => '', 'education' => '', 'hobbies' => [], 'daily_routine' => ''],
            'personality' => ['warmth' => 'neutral', 'dominance' => 'balanced', 'humor' => 'witty', 'openness' => 'selective', 'flirtiness' => 'mild', 'intelligence' => 'average'],
            'backstory_anchors' => [],
            'few_shot_examples' => [],
            'behavior_rules' => ['system_command' => '', 'extra_restrictions' => []],
            'messaging' => ['sends_first_message' => false, 'first_message_templates' => [], 'average_message_length' => 60, 'message_length_min' => 5, 'message_length_max' => 220, 'can_send_voice' => false, 'can_send_photo' => false],
            'memory' => ['memory_active' => true, 'memory_level' => 'medium', 'remembers_user' => true, 'relationship_tracking_active' => true],
            'schedule' => ['timezone' => 'Europe/Istanbul', 'sleep_start_weekday' => '23:30', 'sleep_end_weekday' => '07:30', 'sleep_start_weekend' => '00:30', 'sleep_end_weekend' => '09:30', 'availability_schedules' => []],
            'rate_limits' => ['daily_chat_limit' => 100, 'per_user_daily_message_limit' => 50, 'min_response_seconds' => 3, 'max_response_seconds' => 30, 'random_delay_minutes' => 0],
            'model_config' => ['active' => true, 'provider' => 'gemini', 'model_name' => 'gemini-2.5-flash', 'fallback_provider' => null, 'fallback_model_name' => null, 'temperature' => 0.8, 'top_p' => 0.9, 'max_output_tokens' => 1024],
            'quality_tag' => 'A',
        ];
    }
}
