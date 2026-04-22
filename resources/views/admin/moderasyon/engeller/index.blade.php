@extends('admin.layout.ana')

@section('baslik', 'Engellemeler')

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- Üst navigasyon --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.moderasyon.sikayetler') }}"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Şikayetler
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-sm font-medium text-gray-700">Engellemeler</span>
        </div>

        {{-- İstatistik Kartları --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-medium text-gray-500">Toplam Engelleme</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam']) }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-medium text-blue-600">Bugün</p>
                <p class="mt-1 text-2xl font-bold text-blue-700">{{ number_format($istatistikler['bugun']) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-medium text-indigo-600">Bu Hafta</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format($istatistikler['bu_hafta']) }}</p>
            </div>
        </div>

        {{-- Filtre ve Tablo --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Arama</label>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Kullanıcı adı, e-posta, sebep..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Ara</button>
                @if (request('arama'))
                    <a href="{{ route('admin.moderasyon.engeller') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Temizle</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-3 py-3 text-left font-medium text-gray-500">ID</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Engelleyen</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Engellenen</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Sebep</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tarih</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($engeller as $engel)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3 font-mono text-xs text-gray-400">#{{ $engel->id }}</td>
                                <td class="px-3 py-3">
                                    @if ($engel->engelleyen)
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600">
                                                {{ mb_substr($engel->engelleyen->ad, 0, 1) }}
                                            </div>
                                            <div>
                                                <a href="{{ route('admin.kullanicilar.goster', $engel->engelleyen) }}"
                                                    class="font-medium text-indigo-600 hover:text-indigo-800">
                                                    {{ $engel->engelleyen->ad }} {{ $engel->engelleyen->soyad }}
                                                </a>
                                                <p class="text-xs text-gray-400">{{ $engel->engelleyen->email }}</p>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-gray-400">Silinmiş</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($engel->engellenen)
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center rounded-full bg-red-100 text-xs font-bold text-red-600">
                                                {{ mb_substr($engel->engellenen->ad, 0, 1) }}
                                            </div>
                                            <div>
                                                <a href="{{ route('admin.kullanicilar.goster', $engel->engellenen) }}"
                                                    class="font-medium text-indigo-600 hover:text-indigo-800">
                                                    {{ $engel->engellenen->ad }} {{ $engel->engellenen->soyad }}
                                                </a>
                                                <p class="text-xs text-gray-400">{{ $engel->engellenen->email }}</p>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-gray-400">Silinmiş</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700">{{ $engel->sebep ?: '—' }}</td>
                                <td class="px-3 py-3 text-xs text-gray-500">{{ $engel->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.moderasyon.engeller.kaldir', $engel) }}"
                                        onsubmit="return confirm('Bu engellemeyi kaldırmak istediğinize emin misiniz?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                            Kaldır
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-12 text-center text-gray-400">Engelleme bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($engeller->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $engeller->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
