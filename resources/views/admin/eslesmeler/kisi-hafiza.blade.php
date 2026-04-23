@extends('admin.layout.ana')

@section('baslik', 'AI Hafizasi - ' . ($kullanici->ad ?? $kullanici->kullanici_adi))

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.eslesmeler.goster', $eslesme) }}"
                    class="rounded-lg bg-gray-100 p-2 text-gray-600 hover:bg-gray-200">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Eslesme #{{ $eslesme->id }}</p>
                    <h2 class="text-xl font-bold text-gray-900">{{ $kullanici->ad }} {{ $kullanici->soyad }}</h2>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($eslesme->sohbet)
                    <a href="{{ route('admin.eslesmeler.sohbet', $eslesme) }}"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Sohbet
                    </a>
                @endif
                <a href="{{ route('admin.kullanicilar.goster', $kullanici) }}"
                    class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Profil
                </a>
            </div>
        </div>

        @if ($paneller === [])
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <p class="text-sm font-medium text-gray-700">Bu kisi hakkinda aktif AI hafizasi bulunmuyor.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($paneller as $panel)
                    @include('admin.partials.ai-hafiza-paneli', ['panel' => $panel])
                @endforeach
            </div>
        @endif
    </div>
@endsection
