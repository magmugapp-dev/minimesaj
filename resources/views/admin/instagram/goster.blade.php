@extends('admin.layout.ana')

@section('baslik', 'Instagram: @' . $instagramHesap->instagram_kullanici_adi)

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- Üst Bar --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.instagram.index') }}"
                    class="rounded-lg bg-gray-100 p-2 text-gray-600 hover:bg-gray-200">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-orange-400">
                        <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">
                            {{ '@' }}{{ $instagramHesap->instagram_kullanici_adi }}</h2>
                        @if ($instagramHesap->instagram_profil_id)
                            <p class="font-mono text-xs text-gray-400">ID: {{ $instagramHesap->instagram_profil_id }}</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($instagramHesap->aktif_mi)
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700">
                        <span class="h-2 w-2 rounded-full bg-green-500"></span>
                        Bağlı
                    </span>
                @else
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        Kopuk
                    </span>
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Sol Kolon —  Hesap Bilgileri --}}
            <div class="space-y-6 lg:col-span-2">

                {{-- Hesap Detayları --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900">Hesap Bilgileri</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-xs font-medium text-gray-500">Sahibi</p>
                            @if ($instagramHesap->user)
                                <a href="{{ route('admin.kullanicilar.goster', $instagramHesap->user) }}"
                                    class="mt-1 inline-block font-semibold text-indigo-600 hover:text-indigo-800">
                                    {{ $instagramHesap->user->ad }} {{ $instagramHesap->user->soyad }}
                                </a>
                                <p class="text-xs text-gray-400">
                                    {{ '@' }}{{ $instagramHesap->user->kullanici_adi }}</p>
                            @else
                                <p class="mt-1 text-sm text-gray-400">Silinmiş</p>
                            @endif
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-xs font-medium text-gray-500">Mod Ayarları</p>
                            <div class="mt-2 space-y-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-2 w-2 rounded-full {{ $instagramHesap->oto_yanit_modu ? 'bg-violet-500' : 'bg-gray-300' }}"></span>
                                    <span
                                        class="text-sm {{ $instagramHesap->oto_yanit_modu ? 'font-medium text-violet-700' : 'text-gray-500' }}">Otomatik
                                        Yanıt</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-2 w-2 rounded-full {{ $instagramHesap->yari_oto_modu ? 'bg-amber-500' : 'bg-gray-300' }}"></span>
                                    <span
                                        class="text-sm {{ $instagramHesap->yari_oto_modu ? 'font-medium text-amber-700' : 'text-gray-500' }}">Yarı-Otomatik</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Zaman --}}
                    <div class="mt-4 rounded-lg border border-gray-100 bg-gray-50 p-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Bağlanma Tarihi</p>
                                <p class="text-sm text-gray-900">{{ $instagramHesap->created_at->format('d.m.Y H:i:s') }}
                                </p>
                                <p class="text-xs text-gray-400">{{ $instagramHesap->created_at->diffForHumans() }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500">Son Güncelleme</p>
                                <p class="text-sm text-gray-900">{{ $instagramHesap->updated_at->format('d.m.Y H:i:s') }}
                                </p>
                                <p class="text-xs text-gray-400">{{ $instagramHesap->updated_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Mesaj İstatistikleri --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Mesaj İstatistikleri</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div class="rounded-lg bg-gray-50 p-3 text-center">
                            <p class="text-xs text-gray-500">Toplam</p>
                            <p class="text-xl font-bold text-gray-900">{{ number_format($mesajIstatistikleri['toplam']) }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-blue-50 p-3 text-center">
                            <p class="text-xs text-blue-600">Gelen</p>
                            <p class="text-xl font-bold text-blue-700">{{ number_format($mesajIstatistikleri['gelen']) }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-green-50 p-3 text-center">
                            <p class="text-xs text-green-600">Giden</p>
                            <p class="text-xl font-bold text-green-700">{{ number_format($mesajIstatistikleri['giden']) }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-violet-50 p-3 text-center">
                            <p class="text-xs text-violet-600">AI</p>
                            <p class="text-xl font-bold text-violet-700">{{ number_format($mesajIstatistikleri['ai']) }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-indigo-50 p-3 text-center">
                            <p class="text-xs text-indigo-600">Bugün</p>
                            <p class="text-xl font-bold text-indigo-700">{{ number_format($mesajIstatistikleri['bugun']) }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- AI Görev İstatistikleri --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">AI Görev İstatistikleri</h3>
                        <a href="{{ route('admin.instagram.ai-gorevleri', $instagramHesap) }}"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Tümünü Gör →</a>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div class="rounded-lg bg-gray-50 p-3 text-center">
                            <p class="text-xs text-gray-500">Toplam</p>
                            <p class="text-xl font-bold text-gray-900">
                                {{ number_format($aiGorevIstatistikleri['toplam']) }}</p>
                        </div>
                        <div class="rounded-lg bg-yellow-50 p-3 text-center">
                            <p class="text-xs text-yellow-600">Bekliyor</p>
                            <p class="text-xl font-bold text-yellow-700">
                                {{ number_format($aiGorevIstatistikleri['bekliyor']) }}</p>
                        </div>
                        <div class="rounded-lg bg-blue-50 p-3 text-center">
                            <p class="text-xs text-blue-600">İşleniyor</p>
                            <p class="text-xl font-bold text-blue-700">
                                {{ number_format($aiGorevIstatistikleri['isleniyor']) }}</p>
                        </div>
                        <div class="rounded-lg bg-green-50 p-3 text-center">
                            <p class="text-xs text-green-600">Tamamlandı</p>
                            <p class="text-xl font-bold text-green-700">
                                {{ number_format($aiGorevIstatistikleri['tamamlandi']) }}</p>
                        </div>
                        <div class="rounded-lg bg-red-50 p-3 text-center">
                            <p class="text-xs text-red-600">Başarısız</p>
                            <p class="text-xl font-bold text-red-700">
                                {{ number_format($aiGorevIstatistikleri['basarisiz']) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sağ Kolon — Son Kişiler --}}
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Son Kişiler</h3>
                        <a href="{{ route('admin.instagram.kisiler', $instagramHesap) }}"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Tümünü Gör →</a>
                    </div>
                    @forelse ($sonKisiler as $kisi)
                        <a href="{{ route('admin.instagram.mesajlar', [$instagramHesap, $kisi]) }}"
                            class="mb-2 flex items-center gap-3 rounded-lg border border-gray-100 p-3 hover:bg-gray-50">
                            @if ($kisi->profil_fotografi_url)
                                <img src="{{ $kisi->profil_fotografi_url }}" alt=""
                                    class="h-9 w-9 rounded-full object-cover">
                            @else
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-500">
                                    {{ mb_strtoupper(mb_substr($kisi->instagram_kullanici_adi, 0, 1)) }}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $kisi->gorunen_ad ?: $kisi->instagram_kullanici_adi }}
                                </p>
                                <p class="text-xs text-gray-400">{{ '@' }}{{ $kisi->instagram_kullanici_adi }}
                                </p>
                            </div>
                            @if ($kisi->son_mesaj_tarihi)
                                <p class="text-[10px] text-gray-400">
                                    {{ $kisi->son_mesaj_tarihi->diffForHumans(short: true) }}</p>
                            @endif
                        </a>
                    @empty
                        <p class="py-4 text-center text-sm text-gray-400">Henüz kişi yok</p>
                    @endforelse
                </div>

                {{-- Hızlı Bağlantılar --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900">Hızlı Bağlantılar</h3>
                    <div class="space-y-2">
                        <a href="{{ route('admin.instagram.kisiler', $instagramHesap) }}"
                            class="flex items-center gap-3 rounded-lg border border-gray-100 p-3 hover:bg-gray-50">
                            <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Tüm Kişiler</p>
                                <p class="text-xs text-gray-400">{{ number_format($instagramHesap->kisiler_count) }} kişi
                                </p>
                            </div>
                        </a>
                        <a href="{{ route('admin.instagram.ai-gorevleri', $instagramHesap) }}"
                            class="flex items-center gap-3 rounded-lg border border-gray-100 p-3 hover:bg-gray-50">
                            <svg class="h-5 w-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900">AI Görevleri</p>
                                <p class="text-xs text-gray-400">{{ number_format($instagramHesap->ai_gorevleri_count) }}
                                    görev</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
