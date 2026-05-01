@extends('admin.layout.ana')

@section('baslik', 'Gemini Yonetimi')

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-semibold text-gray-500">Bugunku Uyari</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($warningStats['today']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-semibold text-amber-700">Retry Edilebilir</p>
                <p class="mt-1 text-2xl font-bold text-amber-800">{{ number_format($warningStats['retryable']) }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="text-xs font-semibold text-red-700">Kalici 4xx</p>
                <p class="mt-1 text-2xl font-bold text-red-800">{{ number_format($warningStats['permanent']) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900">API Key Havuzu</h2>
                    <p class="mt-1 text-sm text-gray-500">Aktif ve exhausted olmayan en yuksek oncelikli anahtar kullanilir.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.gemini.keys.store') }}" class="mb-5 grid gap-3 rounded-lg bg-gray-50 p-4 md:grid-cols-5">
                @csrf
                <input name="label" placeholder="Etiket" class="rounded-lg border-gray-300 text-sm md:col-span-1">
                <input name="api_key" placeholder="Yeni Gemini API key" class="rounded-lg border-gray-300 text-sm md:col-span-2">
                <input name="priority" type="number" value="0" class="rounded-lg border-gray-300 text-sm">
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" name="active" value="1" checked class="rounded border-gray-300 text-indigo-600">
                    Aktif
                </label>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 md:col-span-5">Anahtar ekle</button>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Key</th>
                            <th class="px-3 py-3">Oncelik</th>
                            <th class="px-3 py-3">Istek</th>
                            <th class="px-3 py-3">Durum</th>
                            <th class="px-3 py-3">Son Kullanim</th>
                            <th class="px-3 py-3 text-right">Islem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($keys as $key)
                            <tr>
                                <td class="px-3 py-3">
                                    <form id="gemini-key-{{ $key->id }}" method="POST" action="{{ route('admin.gemini.keys.update', $key) }}" class="grid gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input name="label" value="{{ $key->label }}" placeholder="Etiket" class="rounded-lg border-gray-300 text-sm">
                                        <input name="api_key" placeholder="Degistirmek icin yeni key gir" class="rounded-lg border-gray-300 text-xs">
                                    </form>
                                </td>
                                <td class="px-3 py-3">
                                    <input form="gemini-key-{{ $key->id }}" name="priority" type="number" value="{{ $key->priority }}" class="w-24 rounded-lg border-gray-300 text-sm">
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-gray-600">{{ number_format($key->total_requests) }}</td>
                                <td class="px-3 py-3">
                                    <label class="mb-2 flex items-center gap-2 text-xs font-semibold text-gray-700">
                                        <input form="gemini-key-{{ $key->id }}" type="checkbox" name="active" value="1" @checked($key->active) class="rounded border-gray-300 text-indigo-600">
                                        Aktif
                                    </label>
                                    @if ($key->exhausted_until && $key->exhausted_until->isFuture())
                                        <label class="flex items-center gap-2 text-xs text-amber-700">
                                            <input form="gemini-key-{{ $key->id }}" type="checkbox" name="clear_exhausted" value="1" class="rounded border-gray-300 text-amber-600">
                                            Exhausted temizle
                                        </label>
                                        <p class="mt-1 text-xs text-amber-600">{{ $key->exhausted_until->format('d.m.Y H:i') }}</p>
                                    @else
                                        <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Hazir</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500">{{ $key->last_used_at?->format('d.m.Y H:i') ?: '-' }}</td>
                                <td class="px-3 py-3 text-right">
                                    <button form="gemini-key-{{ $key->id }}" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white">Kaydet</button>
                                    <form method="POST" action="{{ route('admin.gemini.keys.destroy', $key) }}" class="mt-2" onsubmit="return confirm('Bu Gemini key silinsin mi?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-semibold text-red-600 hover:text-red-800">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-10 text-center text-gray-400">Henuz API key eklenmemis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Gemini Uyarilari</h2>
                    <p class="mt-1 text-sm text-gray-500">429/5xx retry, diger 4xx kalici hata olarak kaydedilir.</p>
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <input name="error_code" value="{{ request('error_code') }}" placeholder="Kod" class="w-24 rounded-lg border-gray-300 text-sm">
                    <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700">Filtrele</button>
                    @if (request('error_code'))
                        <a href="{{ route('admin.gemini.index') }}" class="text-sm font-semibold text-gray-500">Temizle</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Kod</th>
                            <th class="px-3 py-3">AI</th>
                            <th class="px-3 py-3">Turn</th>
                            <th class="px-3 py-3">Mesaj</th>
                            <th class="px-3 py-3">Tarih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($warnings as $warning)
                            <tr>
                                <td class="px-3 py-3 font-mono text-xs font-bold text-gray-800">{{ $warning->error_code }}</td>
                                <td class="px-3 py-3">{{ $warning->aiUser?->ad ?: '-' }}</td>
                                <td class="px-3 py-3 font-mono text-xs">#{{ $warning->turn_id ?: '-' }}</td>
                                <td class="max-w-xl px-3 py-3 text-xs text-gray-600">{{ $warning->error_message }}</td>
                                <td class="px-3 py-3 text-xs text-gray-500">{{ $warning->occurred_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-10 text-center text-gray-400">Gemini uyarisi yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($warnings->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">{{ $warnings->links() }}</div>
            @endif
        </div>
    </div>
@endsection
