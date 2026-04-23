<!DOCTYPE html>
<html lang="tr" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('baslik', 'Pano') — MiniMesaj Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] {
            display: none !important
        }
    </style>
    @include('admin.partials.studio-styles')
</head>

<body class="h-full bg-gray-50" x-data="{ sidebarAcik: false }">

    {{-- Mobil overlay --}}
    <div x-show="sidebarAcik" x-cloak x-transition:enter="transition-opacity duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" @click="sidebarAcik = false"
        class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="sidebarAcik ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-gray-900 transition-transform duration-300 ease-in-out lg:translate-x-0">

        {{-- Logo --}}
        @php $uygulamaLogosu = \App\Models\Ayar::where('anahtar', 'uygulama_logosu')->value('deger'); @endphp
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            @if ($uygulamaLogosu)
                <img src="{{ asset('storage/' . $uygulamaLogosu) }}" alt="Logo"
                    class="h-8 w-8 rounded-lg object-cover">
            @else
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-500">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
            @endif
            <div>
                <span class="text-base font-bold text-white">MiniMesaj</span>
                <span
                    class="ml-1.5 rounded bg-indigo-500/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-300">Admin</span>
            </div>
            {{-- Mobil kapat --}}
            <button @click="sidebarAcik = false" class="ml-auto text-gray-400 hover:text-white lg:hidden">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Navigasyon --}}
        <nav data-admin-sidebar-nav class="flex-1 overflow-y-auto px-3 py-4">
            <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Ana Menü</p>

            @php
                $ayarMenuGruplari = \App\Support\AdminAyarlari::kategorilerSidebarGruplu();
                $menuler = [
                    [
                        'route' => 'admin.pano',
                        'etiket' => 'Pano',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>',
                    ],
                    [
                        'route' => 'admin.kullanicilar.index',
                        'etiket' => 'Kullanıcılar',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>',
                    ],
                    [
                        'route' => 'admin.ai.index',
                        'etiket' => 'AI Kullanıcılar',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>',
                    ],
                    [
                        'route' => 'admin.influencer.index',
                        'etiket' => 'AI Influencer',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.58-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>',
                    ],
                ];

                $moderasyonMenuler = [
                    [
                        'route' => 'admin.moderasyon.sikayetler',
                        'etiket' => 'Şikayetler',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>',
                    ],
                    [
                        'route' => 'admin.moderasyon.destek-talepleri',
                        'etiket' => 'Destek Talepleri',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3h5.25M6.375 3h11.25A2.625 2.625 0 0120.25 5.625v12.75A2.625 2.625 0 0117.625 21H6.375A2.625 2.625 0 013.75 18.375V5.625A2.625 2.625 0 016.375 3z"/>',
                    ],
                    [
                        'route' => 'admin.eslesmeler.index',
                        'etiket' => 'Eşleşmeler',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>',
                    ],
                ];

                $finansalMenuler = [
                    [
                        'route' => 'admin.finansal.odemeler',
                        'etiket' => 'Finansal',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>',
                    ],
                    [
                        'route' => 'admin.finansal.puan-paketleri.index',
                        'etiket' => 'Puan Paketleri',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M14.25 7.5V6a2.25 2.25 0 00-4.5 0v1.5m-3 0h10.5A2.25 2.25 0 0119.5 9.75v8.25a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 18V9.75A2.25 2.25 0 016.75 7.5zm5.25 4.5h.008v.008H12V12z"/>',
                    ],
                    [
                        'route' => 'admin.finansal.abonelik-paketleri.index',
                        'etiket' => 'Abonelik Paketleri',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.912 3.873 4.274.621-3.093 3.015.73 4.257L12 12.75l-3.823 2.016.73-4.257L5.814 7.494l4.274-.621L12 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 19.5h10.5"/>',
                    ],
                    [
                        'route' => 'admin.hediyeler.index',
                        'etiket' => 'Hediyeler',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v7.5A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75v-7.5m18 0H3m18 0A2.25 2.25 0 0018.75 9H5.25A2.25 2.25 0 003 11.25m9 9.75V9m0 0H8.25A2.25 2.25 0 116 6.75C6 5.507 7.007 4.5 8.25 4.5c1.5 0 2.625 1.5 3.75 4.5zm0 0h3.75A2.25 2.25 0 1018 6.75c0-1.243-1.007-2.25-2.25-2.25-1.5 0-2.625 1.5-3.75 4.5z"/>',
                    ],
                    [
                        'route' => 'admin.finansal.aboneler',
                        'etiket' => 'Aboneler',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75a17.933 17.933 0 01-7.5-1.632z"/>',
                    ],
                    [
                        'route' => 'admin.instagram.index',
                        'etiket' => 'Instagram',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>',
                    ],
                ];

                $sistemMenuler = [
                    [
                        'route' => 'admin.istatistik.index',
                        'etiket' => 'İstatistik',
                        'svg' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
                    ],
                ];
            @endphp

            @foreach ($menuler as $menu)
                @php $aktif = Route::has($menu['route']) && request()->routeIs($menu['route'] . '*'); @endphp
                <a href="{{ Route::has($menu['route']) ? route($menu['route']) : '#' }}"
                    class="group mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ $aktif ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : (Route::has($menu['route']) ? 'text-gray-300 hover:bg-white/10 hover:text-white' : 'text-gray-500 cursor-default') }}">
                    <svg class="h-5 w-5 shrink-0 {{ $aktif ? 'text-white' : (Route::has($menu['route']) ? 'text-gray-400 group-hover:text-gray-300' : 'text-gray-600') }}"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">{!! $menu['svg'] !!}</svg>
                    {{ $menu['etiket'] }}
                    @unless (Route::has($menu['route']))
                        <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] text-gray-500">Yakında</span>
                    @endunless
                </a>
            @endforeach

            <p class="mb-2 mt-5 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Yönetim</p>

            @foreach ($moderasyonMenuler as $menu)
                @php $aktif = Route::has($menu['route']) && request()->routeIs($menu['route'] . '*'); @endphp
                <a href="{{ Route::has($menu['route']) ? route($menu['route']) : '#' }}"
                    class="group mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ $aktif ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : (Route::has($menu['route']) ? 'text-gray-300 hover:bg-white/10 hover:text-white' : 'text-gray-500 cursor-default') }}">
                    <svg class="h-5 w-5 shrink-0 {{ $aktif ? 'text-white' : (Route::has($menu['route']) ? 'text-gray-400 group-hover:text-gray-300' : 'text-gray-600') }}"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">{!! $menu['svg'] !!}</svg>
                    {{ $menu['etiket'] }}
                    @unless (Route::has($menu['route']))
                        <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] text-gray-500">Yakında</span>
                    @endunless
                </a>
            @endforeach

            <p class="mb-2 mt-5 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Finans &
                Entegrasyon</p>

            @foreach ($finansalMenuler as $menu)
                @php $aktif = Route::has($menu['route']) && request()->routeIs($menu['route'] . '*'); @endphp
                <a href="{{ Route::has($menu['route']) ? route($menu['route']) : '#' }}"
                    class="group mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ $aktif ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : (Route::has($menu['route']) ? 'text-gray-300 hover:bg-white/10 hover:text-white' : 'text-gray-500 cursor-default') }}">
                    <svg class="h-5 w-5 shrink-0 {{ $aktif ? 'text-white' : (Route::has($menu['route']) ? 'text-gray-400 group-hover:text-gray-300' : 'text-gray-600') }}"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">{!! $menu['svg'] !!}</svg>
                    {{ $menu['etiket'] }}
                    @unless (Route::has($menu['route']))
                        <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] text-gray-500">Yakında</span>
                    @endunless
                </a>
            @endforeach

            <p class="mb-2 mt-5 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Sistem</p>

            @foreach ($sistemMenuler as $menu)
                @php $aktif = Route::has($menu['route']) && request()->routeIs($menu['route'] . '*'); @endphp
                <a href="{{ Route::has($menu['route']) ? route($menu['route']) : '#' }}"
                    class="group mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                      {{ $aktif ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : (Route::has($menu['route']) ? 'text-gray-300 hover:bg-white/10 hover:text-white' : 'text-gray-500 cursor-default') }}">
                    <svg class="h-5 w-5 shrink-0 {{ $aktif ? 'text-white' : (Route::has($menu['route']) ? 'text-gray-400 group-hover:text-gray-300' : 'text-gray-600') }}"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">{!! $menu['svg'] !!}</svg>
                    {{ $menu['etiket'] }}
                    @unless (Route::has($menu['route']))
                        <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] text-gray-500">Yakında</span>
                    @endunless
                </a>
            @endforeach

            @foreach ($ayarMenuGruplari as $grupEtiketi => $grupMenuleri)
                <p class="mb-2 mt-5 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                    {{ $grupEtiketi }}</p>

                @foreach ($grupMenuleri as $menu)
                    @php
                        $aktif =
                            request()->routeIs('admin.ayarlar.kategori') &&
                            request()->route('kategori') === $menu['slug'];
                    @endphp
                    <a href="{{ route('admin.ayarlar.kategori', ['kategori' => $menu['slug']]) }}"
                        class="group mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                        {{ $aktif ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0 {{ $aktif ? 'text-white' : 'text-gray-400 group-hover:text-gray-300' }}"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">{!! $menu['svg'] !!}</svg>
                        {{ $menu['etiket'] }}
                    </a>
                @endforeach
            @endforeach
        </nav>

        {{-- Sidebar alt -- kullanıcı bilgisi --}}
        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-500/20 text-sm font-bold text-indigo-300">
                    {{ mb_substr(auth()->user()->ad, 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ auth()->user()->ad }}
                        {{ auth()->user()->soyad }}</p>
                    <p class="truncate text-xs text-gray-400">{{ auth()->user()->email }}</p>
                </div>
                <form method="POST" action="{{ route('admin.cikis') }}">
                    @csrf
                    <button type="submit" title="Çıkış Yap"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-white/10 hover:text-red-400 transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Ana İçerik --}}
    <div class="lg:ml-64">
        {{-- Üst Navbar --}}
        <header
            class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-gray-200 bg-white/80 px-4 backdrop-blur-lg sm:px-6 lg:px-8">
            {{-- Mobil menü butonu --}}
            <button @click="sidebarAcik = true"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:hidden">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <h1 class="text-lg font-semibold text-gray-900">@yield('baslik', 'Pano')</h1>

            @php
                $adminNavbarTarih = now()->locale('tr')->isoFormat('D MMMM YYYY, dddd');
                $adminNavbarGunlukBakiyeMetni = number_format($adminNavbarGunlukBakiye ?? 0, 2, ',', '.') . ' TL';
            @endphp

            <div class="ml-auto flex items-center gap-3">
                <div class="admin-navbar-mobile-balance">{{ $adminNavbarGunlukBakiyeMetni }}</div>

                <div class="admin-navbar-meta">

                    <div class="admin-navbar-card">
                        <div class="admin-navbar-card__eyebrow">Tarih</div>
                        <div class="admin-navbar-card__value">{{ $adminNavbarTarih }}</div>
                    </div>

                    <div class="admin-navbar-card admin-navbar-card--balance">
                        <div class="admin-navbar-card__eyebrow">Gunluk bakiye</div>
                        <div class="admin-navbar-card__value">{{ $adminNavbarGunlukBakiyeMetni }}</div>
                    </div>

                </div>
            </div>
        </header>

        {{-- Sayfa İçeriği --}}
        <main class="">
            {{-- Flash mesajlar --}}
            @if (session('basari'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4" x-data="{ goster: true }"
                    x-show="goster" x-transition>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-green-800">{{ session('basari') }}</p>
                        <button @click="goster = false" class="ml-4 rounded text-green-400 hover:text-green-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            @if (session('hata'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4" x-data="{ goster: true }"
                    x-show="goster" x-transition>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-red-800">{{ session('hata') }}</p>
                        <button @click="goster = false" class="ml-4 rounded text-red-400 hover:text-red-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            @if (session('uyari'))
                <div class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4" x-data="{ goster: true }"
                    x-show="goster" x-transition>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-yellow-800">{{ session('uyari') }}</p>
                        <button @click="goster = false" class="ml-4 rounded text-yellow-400 hover:text-yellow-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            @if (session('bilgi'))
                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4" x-data="{ goster: true }"
                    x-show="goster" x-transition>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-blue-800">{{ session('bilgi') }}</p>
                        <button @click="goster = false" class="ml-4 rounded text-blue-400 hover:text-blue-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            @yield('icerik')
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarNav = document.querySelector('[data-admin-sidebar-nav]');

            if (!sidebarNav) {
                return;
            }

            const storageKey = 'admin-sidebar-scroll-top';
            const savedScrollTop = window.sessionStorage.getItem(storageKey);

            if (savedScrollTop !== null) {
                sidebarNav.scrollTop = Number.parseInt(savedScrollTop, 10) || 0;
            }

            const persistSidebarScroll = () => {
                window.sessionStorage.setItem(storageKey, String(sidebarNav.scrollTop));
            };

            sidebarNav.addEventListener('scroll', persistSidebarScroll, {
                passive: true
            });
            sidebarNav.addEventListener('click', (event) => {
                if (event.target.closest('a[href]')) {
                    persistSidebarScroll();
                }
            });
            window.addEventListener('beforeunload', persistSidebarScroll);
        });
    </script>
</body>

</html>
