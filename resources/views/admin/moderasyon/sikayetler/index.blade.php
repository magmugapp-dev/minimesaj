@extends('admin.layout.ana')

@section('baslik', 'Şikayetler')

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- İstatistik Kartları --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-medium text-gray-500">Toplam</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam']) }}</p>
            </div>
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
                <p class="text-xs font-medium text-yellow-600">Bekliyor</p>
                <p class="mt-1 text-2xl font-bold text-yellow-700">{{ number_format($istatistikler['bekliyor']) }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-medium text-blue-600">İnceleniyor</p>
                <p class="mt-1 text-2xl font-bold text-blue-700">{{ number_format($istatistikler['inceleniyor']) }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-medium text-green-600">Çözüldü</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ number_format($istatistikler['cozuldu']) }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="text-xs font-medium text-red-600">Reddedildi</p>
                <p class="mt-1 text-2xl font-bold text-red-700">{{ number_format($istatistikler['reddedildi']) }}</p>
            </div>
        </div>

        {{-- Filtreler ve Toplu İşlem --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4" x-data="{ seciliIdler: [], tumunuSec: false }" x-init="$watch('tumunuSec', val => { seciliIdler = val ? [...document.querySelectorAll('input[name=sikayet_cb]')].map(el => el.value) : [] })">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Arama</label>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Kategori, açıklama, kullanıcı..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Durum</label>
                    <select name="durum"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="bekliyor" {{ request('durum') === 'bekliyor' ? 'selected' : '' }}>Bekliyor</option>
                        <option value="inceleniyor" {{ request('durum') === 'inceleniyor' ? 'selected' : '' }}>İnceleniyor
                        </option>
                        <option value="cozuldu" {{ request('durum') === 'cozuldu' ? 'selected' : '' }}>Çözüldü</option>
                        <option value="reddedildi" {{ request('durum') === 'reddedildi' ? 'selected' : '' }}>Reddedildi
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Kategori</label>
                    <select name="kategori"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        @foreach ($kategoriler as $kat)
                            <option value="{{ $kat }}" {{ request('kategori') === $kat ? 'selected' : '' }}>
                                {{ ucfirst($kat) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Hedef Tipi</label>
                    <select name="hedef_tipi"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="user" {{ request('hedef_tipi') === 'user' ? 'selected' : '' }}>Kullanıcı</option>
                        <option value="mesaj" {{ request('hedef_tipi') === 'mesaj' ? 'selected' : '' }}>Mesaj</option>
                    </select>
                </div>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
                @if (request()->hasAny(['arama', 'durum', 'kategori', 'hedef_tipi']))
                    <a href="{{ route('admin.moderasyon.sikayetler') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Temizle</a>
                @endif
            </form>

            {{-- Toplu işlem --}}
            <div x-show="seciliIdler.length > 0" x-cloak
                class="mb-4 flex items-center gap-3 rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3">
                <span class="text-sm font-medium text-indigo-700" x-text="seciliIdler.length + ' şikayet seçildi'"></span>
                <form method="POST" action="{{ route('admin.moderasyon.sikayetler.toplu-durum') }}"
                    class="flex items-center gap-2">
                    @csrf
                    <template x-for="id in seciliIdler" :key="id">
                        <input type="hidden" name="sikayet_idler[]" :value="id">
                    </template>
                    <select name="durum" required class="rounded-lg border border-indigo-300 px-3 py-1.5 text-sm">
                        <option value="bekliyor">Bekliyor</option>
                        <option value="inceleniyor">İnceleniyor</option>
                        <option value="cozuldu">Çözüldü</option>
                        <option value="reddedildi">Reddedildi</option>
                    </select>
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Uygula</button>
                </form>
            </div>

            {{-- Tablo --}}
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-3 py-3 text-left">
                                <input type="checkbox" x-model="tumunuSec"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">ID</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Şikayet Eden</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Hedef</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Kategori</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Durum</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tarih</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sikayetler as $sikayet)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3">
                                    <input type="checkbox" name="sikayet_cb" value="{{ $sikayet->id }}"
                                        x-model="seciliIdler"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-gray-400">#{{ $sikayet->id }}</td>
                                <td class="px-3 py-3">
                                    @if ($sikayet->sikayetEden)
                                        <a href="{{ route('admin.kullanicilar.goster', $sikayet->sikayet_eden_user_id) }}"
                                            class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $sikayet->sikayetEden->ad }} {{ $sikayet->sikayetEden->soyad }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">Silinmiş</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($sikayet->hedef_tipi === 'user')
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0" />
                                            </svg>
                                            Kullanıcı #{{ $sikayet->hedef_id }}
                                        </span>
                                    @else
                                        <div class="space-y-1">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                                </svg>
                                                Mesaj #{{ $sikayet->hedef_id }}
                                            </span>
                                            <p class="max-w-xs truncate text-xs text-gray-500">
                                                {{ $sikayet->hedefMesaj?->mesaj_metni ?: 'Mesaj metni yok.' }}
                                            </p>
                                            @if ($sikayet->hedefMesaj?->gonderen)
                                                <p class="text-xs text-gray-400">
                                                    Gönderen: {{ $sikayet->hedefMesaj->gonderen->ad }}
                                                    {{ $sikayet->hedefMesaj->gonderen->soyad }}
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ ucfirst($sikayet->kategori) }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($sikayet->durum === 'bekliyor')
                                        <span
                                            class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">Bekliyor</span>
                                    @elseif ($sikayet->durum === 'inceleniyor')
                                        <span
                                            class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">İnceleniyor</span>
                                    @elseif ($sikayet->durum === 'cozuldu')
                                        <span
                                            class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Çözüldü</span>
                                    @else
                                        <span
                                            class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Reddedildi</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500">
                                    {{ $sikayet->created_at->format('d.m.Y H:i') }}</td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('admin.moderasyon.sikayetler.goster', $sikayet) }}"
                                        class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200">
                                        İncele
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-12 text-center text-gray-400">Şikayet bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($sikayetler->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $sikayetler->links() }}
                </div>
            @endif
        </div>

        {{-- Engeller bağlantısı --}}
        <div class="flex justify-end">
            <a href="{{ route('admin.moderasyon.engeller') }}"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                Engelleme Listesi
            </a>
        </div>
    </div>
@endsection
