@extends('admin.layout.ana')

@section('baslik', 'AI Karakterler')

@section('icerik')
    <div class="space-y-6">
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Flutter AI</div>
                    <h1 class="mt-1 text-3xl font-bold text-gray-900">AI karakterler</h1>
                    <p class="mt-2 text-sm text-gray-500">Laravel karakter, prompt, zamanlama ve relay'i yonetir; cevap uretimi Flutter'da calisir.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.ai.ekle') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Yeni karakter</a>
                    <a href="{{ route('admin.ai.import') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">ZIP import</a>
                </div>
            </div>
        </section>

        @if (session('basari'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('basari') }}</div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white shadow">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Karakter listesi</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Karakter</th>
                            <th class="px-5 py-3">Dil</th>
                            <th class="px-5 py-3">Model</th>
                            <th class="px-5 py-3">Durum</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($characters as $character)
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900">{{ $character->display_name ?: $character->character_id }}</div>
                                    <div class="text-xs text-gray-500">{{ $character->character_id }} · v{{ $character->character_version }}</div>
                                </td>
                                <td class="px-5 py-4">{{ $character->primary_language_name }} ({{ $character->primary_language_code }})</td>
                                <td class="px-5 py-4">{{ $character->model_name }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $character->active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $character->active ? 'Aktif' : 'Pasif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('admin.ai.duzenle', $character) }}" class="text-sm font-semibold text-indigo-600">Duzenle</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">Henüz AI karakter yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4">{{ $characters->links() }}</div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.ai.prompt.update') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Global prompt</h2>
                <input name="version" value="{{ old('version', $prompt?->version ?? 'global-v1') }}" class="mt-4 w-full rounded-lg border-gray-300 text-sm" placeholder="Versiyon">
                <textarea name="prompt_xml" rows="12" class="mt-3 w-full rounded-lg border-gray-300 font-mono text-xs">{{ old('prompt_xml', $prompt?->prompt_xml ?? '') }}</textarea>
                <button class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Prompt kaydet</button>
            </form>

            <form method="POST" action="{{ route('admin.ai.thresholds.update') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900">Blok esikleri</h2>
                @php($defaults = ['absolute_violation' => 1, 'underage_user' => 1, 'abuse' => 2, 'harassment' => 3, 'escalation_max' => 3])
                @foreach ($defaults as $category => $fallback)
                    @php($current = $thresholds->firstWhere('category', $category)?->threshold ?? $fallback)
                    <label class="mt-4 block text-sm font-medium text-gray-700">
                        {{ $category }}
                        <input type="number" name="thresholds[{{ $category }}]" value="{{ $current }}" min="1" max="99" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                @endforeach
                <button class="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Esikleri kaydet</button>
            </form>
        </section>
    </div>
@endsection
