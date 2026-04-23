@php
    $links = [
        [
            'route' => 'admin.ai.index',
            'matches' => ['admin.ai.index', 'admin.ai.goster', 'admin.ai.duzenle', 'admin.ai.guncelle'],
            'title' => 'Studio',
        ],
        [
            'route' => 'admin.ai.states',
            'matches' => ['admin.ai.states'],
            'title' => 'Sohbet Durumlari',
        ],
        [
            'route' => 'admin.ai.memories',
            'matches' => ['admin.ai.memories'],
            'title' => 'Hafiza',
        ],
        [
            'route' => 'admin.ai.traces',
            'matches' => ['admin.ai.traces'],
            'title' => 'AI Trace',
        ],
    ];
@endphp

<div class="ai-studio-nav">
    @foreach ($links as $link)
        @php $active = request()->routeIs(...$link['matches']); @endphp
        <a href="{{ route($link['route']) }}"
            class="ai-studio-link {{ $active ? 'ai-studio-link--active' : '' }}">
            <div class="flex items-center justify-between gap-4">
                <div class="ai-studio-link__title">{{ $link['title'] }}</div>
                <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-teal-700' : 'text-slate-400' }}" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </a>
    @endforeach
</div>