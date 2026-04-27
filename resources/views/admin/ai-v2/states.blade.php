@extends('admin.layout.ana')

@section('baslik', 'AI Studio Runtime')

@section('icerik')
    <div class="space-y-6 p-6">
        <section class="flex flex-col gap-2">
            <div class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Runtime</div>
            <h1 class="text-3xl font-bold text-gray-900">Sohbet Durumlari</h1>
        </section>

        <nav class="flex gap-2 border-b border-gray-200">
            <a href="{{ route('admin.ai.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 border-b-2 border-transparent hover:border-gray-300">AI
                Studio</a>
            <a href="{{ route('admin.ai.states') }}"
                class="px-4 py-2 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600">Durumlar</a>
            <a href="{{ route('admin.ai.traces') }}"
                class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 border-b-2 border-transparent hover:border-gray-300">Tum
                Kayitlar</a>
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

        @if ($states->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-6 py-12 text-center text-sm text-gray-500">
                Kayit yok.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($states as $state)
                    @php
                        $durumBadge = match ($state->ai_durumu) {
                            'typing' => 'bg-blue-100 text-blue-700',
                            'queued' => 'bg-yellow-100 text-yellow-700',
                            'blocked' => 'bg-red-100 text-red-700',
                            default => 'bg-gray-100 text-gray-700',
                        };
                    @endphp
                    <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between mb-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ $state->aiUser?->ad }}
                                    {{ $state->aiUser?->soyad }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $state->kanal }} / {{ $state->hedef_tipi }}
                                    #{{ $state->hedef_id }}</div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $durumBadge }}">{{ $state->ai_durumu }}</span>
                        </div>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="rounded-lg bg-gray-50 p-3">
                                <div class="text-xs font-semibold text-gray-700">Ruh Hali</div>
                                <div class="mt-1 text-sm font-medium text-gray-900">{{ $state->ruh_hali }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <div class="text-xs font-semibold text-gray-700">Son Duygu</div>
                                <div class="mt-1 text-sm font-medium text-gray-900">
                                    {{ $state->son_kullanici_duygusu ?: '-' }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <div class="text-xs font-semibold text-gray-700">Zaman</div>
                                <div class="mt-1 text-sm font-medium text-gray-900">
                                    {{ optional($state->durum_guncellendi_at)->format('H:i:s') }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <div class="text-xs font-semibold text-gray-700">Samimiyet</div>
                                <div class="mt-1 text-sm font-medium text-gray-900">{{ $state->samimiyet_puani }}/10</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <div class="text-xs font-semibold text-gray-700">Ilgi</div>
                                <div class="mt-1 text-sm font-medium text-gray-900">{{ $state->ilgi_puani }}/10</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <div class="text-xs font-semibold text-gray-700">Guven</div>
                                <div class="mt-1 text-sm font-medium text-gray-900">{{ $state->guven_puani }}/10</div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $states->links() }}
            </div>
        @endif
    </div>
@endsection
