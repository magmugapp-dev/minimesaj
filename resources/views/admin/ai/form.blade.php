@extends('admin.layout.ana')

@section('baslik', $character ? 'AI Duzenle' : 'AI Ekle')

@section('icerik')
    <form method="POST" enctype="multipart/form-data" action="{{ $character ? route('admin.ai.guncelle', $character) : route('admin.ai.kaydet') }}" class="space-y-6">
        @csrf
        @if ($character)
            @method('PUT')
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow">
            <h1 class="text-2xl font-bold text-gray-900">{{ $character ? 'AI karakter duzenle' : 'Yeni AI karakter' }}</h1>
            <p class="mt-2 text-sm text-gray-500">Form bv1.0 JSON kaynagini saklar. Flutter bu JSON'u prompt'a cevirir.</p>
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow lg:col-span-2">
                <label class="block text-sm font-semibold text-gray-700">Karakter JSON</label>
                <textarea name="character_json" rows="28" class="mt-2 w-full rounded-lg border-gray-300 font-mono text-xs">{{ old('character_json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}</textarea>
            </div>

            <div class="space-y-4 rounded-lg border border-gray-200 bg-white p-5 shadow">
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $character?->active ?? true))>
                    Aktif
                </label>
                <label class="block text-sm font-semibold text-gray-700">Model
                    <input name="model_name" value="{{ old('model_name', $character?->model_name ?? 'gemini-2.5-flash') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </label>
                <label class="block text-sm font-semibold text-gray-700">Temperature
                    <input type="number" step="0.01" name="temperature" value="{{ old('temperature', $character?->temperature ?? 0.8) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </label>
                <label class="block text-sm font-semibold text-gray-700">Top P
                    <input type="number" step="0.01" name="top_p" value="{{ old('top_p', $character?->top_p ?? 0.9) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </label>
                <label class="block text-sm font-semibold text-gray-700">Max token
                    <input type="number" name="max_output_tokens" value="{{ old('max_output_tokens', $character?->max_output_tokens ?? 1024) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </label>
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" name="reengagement_active" value="1" @checked(old('reengagement_active', $character?->reengagement_active ?? false))>
                    Tekrar yazma aktif
                </label>
                <label class="block text-sm font-semibold text-gray-700">Sessizlik saati
                    <input type="number" name="reengagement_after_hours" value="{{ old('reengagement_after_hours', $character?->reengagement_after_hours ?? 168) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </label>
                <label class="block text-sm font-semibold text-gray-700">Gunluk limit
                    <input type="number" name="reengagement_daily_limit" value="{{ old('reengagement_daily_limit', $character?->reengagement_daily_limit ?? 1) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </label>
                <label class="block text-sm font-semibold text-gray-700">Re-engagement template JSON
                    <textarea name="reengagement_templates" rows="5" class="mt-1 w-full rounded-lg border-gray-300 font-mono text-xs">{{ old('reengagement_templates', json_encode($character?->reengagement_templates ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
                </label>
                <label class="block text-sm font-semibold text-gray-700">Profil fotografi
                    <input type="file" name="profile_image" accept="image/*" class="mt-1 w-full text-sm">
                </label>
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Kaydet</button>
            </div>
        </section>
    </form>
@endsection
