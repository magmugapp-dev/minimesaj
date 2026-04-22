@extends('admin.layout.ana')

@section('baslik', 'Beğeniler')

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- Üst navigasyon --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.eslesmeler.index') }}"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Eşleşmeler
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-sm font-medium text-gray-700">Beğeniler</span>
        </div>

        {{-- İstatistik Kartları --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-medium text-gray-500">Toplam Beğeni</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam']) }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-medium text-green-600">Karşılıklı</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ number_format($istatistikler['karsilikli']) }}</p>
            </div>
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
                <p class="text-xs font-medium text-yellow-600">Görülmemiş</p>
                <p class="mt-1 text-2xl font-bold text-yellow-700">{{ number_format($istatistikler['gorulmemis']) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-medium text-indigo-600">Bugün</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format($istatistikler['bugun']) }}</p>
            </div>
        </div>

        {{-- Filtre ve Tablo --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Arama</label>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Kullanıcı adı, e-posta..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Eşleşme</label>
                    <select name="eslesme"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="evet" {{ request('eslesme') === 'evet' ? 'selected' : '' }}>Eşleşti</option>
                        <option value="hayir" {{ request('eslesme') === 'hayir' ? 'selected' : '' }}>Eşleşmedi</option>
                    </select>
                </div>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
                @if (request()->hasAny(['arama', 'eslesme']))
                    <a href="{{ route('admin.eslesmeler.begeniler') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Temizle</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-3 py-3 text-left font-medium text-gray-500">ID</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Beğenen</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-500"></th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Beğenilen</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Eşleşme</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Görüldü</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tarih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($begeniler as $begeni)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3 font-mono text-xs text-gray-400">#{{ $begeni->id }}</td>
                                <td class="px-3 py-3">
                                    @if ($begeni->begenen)
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600">
                                                {{ mb_substr($begeni->begenen->ad, 0, 1) }}
                                            </div>
                                            <a href="{{ route('admin.kullanicilar.goster', $begeni->begenen) }}"
                                                class="font-medium text-indigo-600 hover:text-indigo-800">
                                                {{ $begeni->begenen->ad }} {{ $begeni->begenen->soyad }}
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-gray-400">Silinmiş</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <svg class="mx-auto h-5 w-5 text-pink-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                    </svg>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($begeni->begenilen)
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center rounded-full bg-pink-100 text-xs font-bold text-pink-600">
                                                {{ mb_substr($begeni->begenilen->ad, 0, 1) }}
                                            </div>
                                            <a href="{{ route('admin.kullanicilar.goster', $begeni->begenilen) }}"
                                                class="font-medium text-indigo-600 hover:text-indigo-800">
                                                {{ $begeni->begenilen->ad }} {{ $begeni->begenilen->soyad }}
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-gray-400">Silinmiş</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($begeni->eslesmeye_donustu_mu)
                                        <span
                                            class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Eşleşti</span>
                                    @else
                                        <span
                                            class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Hayır</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($begeni->goruldu_mu)
                                        <span
                                            class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">Görüldü</span>
                                    @else
                                        <span
                                            class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">Yeni</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500">{{ $begeni->created_at->format('d.m.Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-12 text-center text-gray-400">Beğeni bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($begeniler->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $begeniler->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
