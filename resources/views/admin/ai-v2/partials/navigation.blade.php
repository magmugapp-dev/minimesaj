@php
    $links = [
        [
            'route' => 'admin.ai.index',
            'matches' => ['admin.ai.index', 'admin.ai.ekle', 'admin.ai.kaydet', 'admin.ai.goster', 'admin.ai.duzenle', 'admin.ai.guncelle'],
            'title' => 'Ana Sayfa',
        ],
        [
            'route' => 'admin.ai.states',
            'matches' => ['admin.ai.states'],
            'title' => 'Canli Durumlar',
        ],
        [
            'route' => 'admin.ai.memories',
            'matches' => ['admin.ai.memories'],
            'title' => 'Hafiza',
        ],
        [
            'route' => 'admin.ai.traces',
            'matches' => ['admin.ai.traces'],
            'title' => 'Kayitlar',
        ],
    ];
@endphp

<div class="flex flex-wrap gap-2">
    @foreach ($links as $link)
        @php $active = request()->routeIs(...$link['matches']); @endphp
        <a href="{{ route($link['route']) }}"
            class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium transition-colors {{ $active ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
            {{ $link['title'] }}
        </a>
    @endforeach
</div>
