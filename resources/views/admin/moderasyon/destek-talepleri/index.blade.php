@extends('admin.layout.ana')

@section('baslik', 'Destek Talepleri')

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.pano') }}"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Pano
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-sm font-medium text-gray-700">Destek Talepleri</span>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-medium text-gray-500">Toplam</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-medium text-amber-600">Yeni</p>
                <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($istatistikler['yeni']) }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-medium text-blue-600">İnceleniyor</p>
                <p class="mt-1 text-2xl font-bold text-blue-700">{{ number_format($istatistikler['inceleniyor']) }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-medium text-green-600">Çözüldü</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ number_format($istatistikler['cozuldu']) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div class="min-w-50 flex-1">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Arama</label>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Kullanıcı, e-posta veya mesaj..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Durum</label>
                    <select name="durum"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="yeni" {{ request('durum') === 'yeni' ? 'selected' : '' }}>Yeni</option>
                        <option value="inceleniyor" {{ request('durum') === 'inceleniyor' ? 'selected' : '' }}>İnceleniyor
                        </option>
                        <option value="cozuldu" {{ request('durum') === 'cozuldu' ? 'selected' : '' }}>Çözüldü</option>
                    </select>
                </div>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
                @if (request()->hasAny(['arama', 'durum']))
                    <a href="{{ route('admin.moderasyon.destek-talepleri') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Temizle</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-3 py-3 text-left font-medium text-gray-500">ID</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Kullanıcı</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Mesaj</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Durum</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tarih</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($destekTalepleri as $talep)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3 font-mono text-xs text-gray-400">#{{ $talep->id }}</td>
                                <td class="px-3 py-3">
                                    @if ($talep->user)
                                        <div>
                                            <a href="{{ route('admin.kullanicilar.goster', $talep->user) }}"
                                                class="font-medium text-indigo-600 hover:text-indigo-800">
                                                {{ $talep->user->ad }} {{ $talep->user->soyad }}
                                            </a>
                                            <p class="text-xs text-gray-400">{{ $talep->user->email }}</p>
                                        </div>
                                    @else
                                        <span class="text-gray-400">Silinmiş kullanıcı</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700">
                                    {{ \Illuminate\Support\Str::limit($talep->mesaj, 90) }}
                                </td>
                                <td class="px-3 py-3">
                                    @if ($talep->durum === 'yeni')
                                        <span
                                            class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">Yeni</span>
                                    @elseif ($talep->durum === 'inceleniyor')
                                        <span
                                            class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700">İnceleniyor</span>
                                    @else
                                        <span
                                            class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">Çözüldü</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500">{{ $talep->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('admin.moderasyon.destek-talepleri.goster', $talep) }}"
                                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                        Görüntüle
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-12 text-center text-gray-400">Destek talebi bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($destekTalepleri->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $destekTalepleri->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
