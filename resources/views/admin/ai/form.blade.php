@extends('admin.layout.ana')

@section('baslik', $character ? 'AI Duzenle' : 'AI Ekle')

@php
    $f = fn (string $key, mixed $default = null) => old($key, data_get($formData, $key, $default));
@endphp

@section('icerik')
    <form method="POST" enctype="multipart/form-data" action="{{ $character ? route('admin.ai.guncelle', $character) : route('admin.ai.kaydet') }}" class="space-y-6">
        @csrf
        @if ($character)
            @method('PUT')
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $character ? 'AI karakter duzenle' : 'Yeni AI karakter' }}</h1>
                    <p class="mt-2 text-sm text-gray-500">Form alanlari bv1.0 karakter JSON'unu gunceller; bilinmeyen JSON alanlari korunur.</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $character?->active ?? true))>
                    Aktif
                </label>
            </div>
        </section>

        @if ($errors->any())
            <section class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ $errors->first() }}
            </section>
        @endif

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Kimlik</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-gray-700">Character ID
                        <input name="character_id" value="{{ $f('character_id') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Versiyon
                        <input type="number" name="character_version" value="{{ $f('character_version', 1) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Schema
                        <input name="schema_version" value="{{ $f('schema_version', 'bv1.0') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Kullanici adi
                        <input name="username" value="{{ $f('username') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Ad
                        <input name="first_name" value="{{ $f('first_name') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Soyad
                        <input name="last_name" value="{{ $f('last_name') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Cinsiyet
                        <select name="gender" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            @foreach (['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $value => $label)
                                <option value="{{ $value }}" @selected($f('gender', 'female') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Dogum yili
                        <input type="number" name="birth_year" value="{{ $f('birth_year') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Ulke
                        <input name="country" value="{{ $f('country') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Sehir
                        <input name="city" value="{{ $f('city') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Ilce
                        <input name="district" value="{{ $f('district') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Kalite etiketi
                        <input name="quality_tag" value="{{ $f('quality_tag', 'A') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Profil ve dil</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-gray-700">Dil kodu
                        <input name="primary_language_code" value="{{ $f('primary_language_code', 'tr') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Dil adi
                        <input name="primary_language_name" value="{{ $f('primary_language_name', 'Turkish') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700 sm:col-span-2">Tagline
                        <input name="tagline" value="{{ $f('tagline') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700 sm:col-span-2">Meslek
                        <input name="occupation" value="{{ $f('occupation') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700 sm:col-span-2">Hobiler
                        <textarea name="hobbies" rows="4" class="mt-1 w-full rounded-lg border-gray-300 text-sm">{{ $f('hobbies') }}</textarea>
                    </label>
                    <label class="block text-sm font-semibold text-gray-700 sm:col-span-2">Profil fotografi
                        <input type="file" name="profile_image" accept="image/*" class="mt-1 w-full text-sm">
                    </label>
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Kisilik</h2>
                <div class="mt-4 space-y-3">
                    @foreach (['warmth' => 'Warmth', 'dominance' => 'Dominance', 'humor' => 'Humor', 'openness' => 'Openness', 'flirtiness' => 'Flirtiness', 'intelligence' => 'Intelligence'] as $key => $label)
                        <label class="block text-sm font-semibold text-gray-700">{{ $label }}
                            <input name="{{ $key }}" value="{{ $f($key) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Mesaj ve zamanlama</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-gray-700">Ortalama uzunluk
                        <input type="number" name="average_message_length" value="{{ $f('average_message_length', 60) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Min uzunluk
                        <input type="number" name="message_length_min" value="{{ $f('message_length_min', 5) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Max uzunluk
                        <input type="number" name="message_length_max" value="{{ $f('message_length_max', 220) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Timezone
                        <input name="timezone" value="{{ $f('timezone', 'Europe/Istanbul') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" name="can_send_voice" value="1" @checked($f('can_send_voice', false))>
                        Ses gonderebilir
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" name="can_send_photo" value="1" @checked($f('can_send_photo', false))>
                        Fotograf gonderebilir
                    </label>
                    @foreach (['sleep_start_weekday' => 'Hafta ici uyku baslangic', 'sleep_end_weekday' => 'Hafta ici uyku bitis', 'sleep_start_weekend' => 'Hafta sonu uyku baslangic', 'sleep_end_weekend' => 'Hafta sonu uyku bitis'] as $key => $label)
                        <label class="block text-sm font-semibold text-gray-700">{{ $label }}
                            <input name="{{ $key }}" value="{{ $f($key) }}" placeholder="23:30" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Limitler ve model</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-gray-700">Gunluk chat limit
                        <input type="number" name="daily_chat_limit" value="{{ $f('daily_chat_limit', 100) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Kullanici gunluk limit
                        <input type="number" name="per_user_daily_message_limit" value="{{ $f('per_user_daily_message_limit', 50) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Min cevap saniye
                        <input type="number" name="min_response_seconds" value="{{ $f('min_response_seconds', 3) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Max cevap saniye
                        <input type="number" name="max_response_seconds" value="{{ $f('max_response_seconds', 30) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700 sm:col-span-2">Model
                        <input name="model_name" value="{{ $f('model_name', 'gemini-2.5-flash') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Temperature
                        <input type="number" step="0.01" name="temperature" value="{{ $f('temperature', 0.8) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Top P
                        <input type="number" step="0.01" name="top_p" value="{{ $f('top_p', 0.9) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700 sm:col-span-2">Max token
                        <input type="number" name="max_output_tokens" value="{{ $f('max_output_tokens', 1024) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Tekrar yazma</h2>
            <div class="mt-4 grid gap-4 lg:grid-cols-4">
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" name="reengagement_active" value="1" @checked($f('reengagement_active', false))>
                    Aktif
                </label>
                <label class="block text-sm font-semibold text-gray-700">Sessizlik saati
                    <input type="number" name="reengagement_after_hours" value="{{ $f('reengagement_after_hours', 168) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </label>
                <label class="block text-sm font-semibold text-gray-700">Gunluk limit
                    <input type="number" name="reengagement_daily_limit" value="{{ $f('reengagement_daily_limit', 1) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </label>
                <label class="block text-sm font-semibold text-gray-700 lg:col-span-4">Re-engagement template JSON
                    <textarea name="reengagement_templates" rows="4" class="mt-1 w-full rounded-lg border-gray-300 font-mono text-xs">{{ $f('reengagement_templates', '[]') }}</textarea>
                </label>
            </div>
        </section>

        <div class="flex justify-end">
            <button class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white">Kaydet</button>
        </div>
    </form>
@endsection
