@extends('admin.layout.ana')

@section('baslik', 'AI Studio Trace')

@section('icerik')
    <div class="space-y-6 p-6">
        <section class="flex flex-col gap-2">
            <div class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Trace</div>
            <h1 class="text-3xl font-bold text-gray-900">AI Trace</h1>
        </section>

        <nav class="flex gap-2 border-b border-gray-200">
            <a href="{{ route('admin.ai.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 border-b-2 border-transparent hover:border-gray-300">AI
                Studio</a>
            <a href="{{ route('admin.ai.index', '#states') }}"
                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 border-b-2 border-transparent hover:border-gray-300">Durumlar</a>
            <a href="{{ route('admin.ai.traces') }}"
                class="px-4 py-2 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600">Tum Kayitlar</a>
            <a href="{{ route('admin.ai.memories') }}"
                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 border-b-2 border-transparent hover:border-gray-300">Hafiza</a>
        </nav>

        <form method="GET" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid gap-4 md:grid-cols-3">
                <label class="block">
                    <span class="mb-2 text-xs font-semibold uppercase tracking-widest text-gray-500">AI Kullanici</span>
                    <select name="ai_user_id"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        <option value="">Tum AI kullanicilar</option>
                        @foreach ($aiUsers as $aiUser)
                            <option value="{{ $aiUser->id }}" @selected(request('ai_user_id') == $aiUser->id)>{{ $aiUser->ad }}
                                {{ $aiUser->soyad }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-2 text-xs font-semibold uppercase tracking-widest text-gray-500">Kanal</span>
                    <select name="kanal"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        <option value="">Tum kanallar</option>
                        <option value="dating" @selected(request('kanal') === 'dating')>Dating</option>
                        <option value="instagram" @selected(request('kanal') === 'instagram')>Instagram</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button type="submit"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition">Filtrele</button>
                </div>
            </div>
        </form>

        @if ($traces->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-6 py-12 text-center text-sm text-gray-500">
                Kayit yok.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($traces as $trace)
                    @php
                        $traceBadge = match ($trace->durum) {
                            'completed' => 'bg-green-100 text-green-700',
                            'failed' => 'bg-red-100 text-red-700',
                            'processing' => 'bg-yellow-100 text-yellow-700',
                            default => 'bg-gray-100 text-gray-700',
                        };
                    @endphp
                    <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between mb-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ $trace->aiUser?->ad }}
                                    {{ $trace->aiUser?->soyad }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $trace->kanal }} / {{ $trace->turn_type }} /
                                    {{ optional($trace->created_at)->format('d.m.Y H:i') }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $traceBadge }}">{{ $trace->durum }}</span>
                                <span
                                    class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">{{ $trace->model_adi ?: '-' }}</span>
                                <span
                                    class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700">{{ $trace->giris_token_sayisi ?: 0 }}
                                    / {{ $trace->cikis_token_sayisi ?: 0 }}</span>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 mb-4">
                            <div class="rounded-lg bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-widest text-gray-700 mb-2">Yorumlama
                                </div>
                                <pre class="text-xs text-gray-600 overflow-x-auto max-h-40 bg-white p-2 rounded border border-gray-200">{{ json_encode($trace->yorumlama, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-widest text-gray-700 mb-2">Cevap Plani
                                </div>
                                <pre class="text-xs text-gray-600 overflow-x-auto max-h-40 bg-white p-2 rounded border border-gray-200">{{ json_encode($trace->cevap_plani, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4 mb-4">
                            <div class="text-xs font-semibold uppercase tracking-widest text-gray-700 mb-2">Final Cevap
                            </div>
                            <div
                                class="bg-white p-3 rounded border border-gray-200 text-sm text-gray-800 max-h-48 overflow-y-auto">
                                {{ $trace->cevap_metni ?: '-' }}
                            </div>
                        </div>

                        <details class="border-t border-gray-200 pt-4">
                            <summary class="cursor-pointer text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                Detaylı Bilgi (Hafiza, Çelişki, Evaluator)</summary>
                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-widest text-gray-700 mb-2">Hafiza /
                                        Celiski</div>
                                    <pre class="text-xs text-gray-600 overflow-x-auto bg-white p-2 rounded border border-gray-200">{{ json_encode(
                                        [
                                            'extraction' => data_get($trace->metadata, 'memory_extraction'),
                                            'stored_memory_ids' => data_get($trace->metadata, 'stored_memory_ids'),
                                            'contradictions' => data_get($trace->metadata, 'contradictions'),
                                        ],
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                                    ) }}</pre>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-4 md:col-span-2">
                                    <div class="text-xs font-semibold uppercase tracking-widest text-gray-700 mb-2">
                                        Evaluator</div>
                                    <pre class="text-xs text-gray-600 overflow-x-auto bg-white p-2 rounded border border-gray-200">{{ json_encode($trace->degerlendirme, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </div>
                            </div>
                        </details>
                    </article>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $traces->links() }}
            </div>
        @endif
    </div>
@endsection
