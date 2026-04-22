@extends('admin.layout.ana')

@section('baslik', 'AI Görevleri — @' . $instagramHesap->instagram_kullanici_adi)

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- Üst Bar --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.instagram.goster', $instagramHesap) }}"
                class="rounded-lg bg-gray-100 p-2 text-gray-600 hover:bg-gray-200">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <div>
                <h2 class="text-lg font-bold text-gray-900">
                    {{ '@' }}{{ $instagramHesap->instagram_kullanici_adi }} — AI Görevleri</h2>
                <p class="text-sm text-gray-500">{{ $gorevler->total() }} görev</p>
            </div>
        </div>

        {{-- Filtre --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Durum</label>
                    <select name="durum"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="bekliyor" {{ request('durum') === 'bekliyor' ? 'selected' : '' }}>Bekliyor</option>
                        <option value="isleniyor" {{ request('durum') === 'isleniyor' ? 'selected' : '' }}>İşleniyor
                        </option>
                        <option value="tamamlandi" {{ request('durum') === 'tamamlandi' ? 'selected' : '' }}>Tamamlandı
                        </option>
                        <option value="basarisiz" {{ request('durum') === 'basarisiz' ? 'selected' : '' }}>Başarısız
                        </option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
                    <a href="{{ route('admin.instagram.ai-gorevleri', $instagramHesap) }}"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Temizle</a>
                </div>
            </form>

            {{-- Tablo --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500">
                            <th class="px-3 py-3 text-left font-medium text-gray-500">#</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Kişi</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Durum</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Yanıt</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Model</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-500">Deneme</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Hata</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tarih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($gorevler as $gorev)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3 font-mono text-xs text-gray-400">#{{ $gorev->id }}</td>
                                <td class="px-3 py-3">
                                    @if ($gorev->kisi)
                                        <a href="{{ route('admin.instagram.mesajlar', [$instagramHesap, $gorev->kisi]) }}"
                                            class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ '@' }}{{ $gorev->kisi->instagram_kullanici_adi }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($gorev->durum === 'tamamlandi')
                                        <span
                                            class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Tamamlandı</span>
                                    @elseif ($gorev->durum === 'bekliyor')
                                        <span
                                            class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">Bekliyor</span>
                                    @elseif ($gorev->durum === 'isleniyor')
                                        <span
                                            class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">İşleniyor</span>
                                    @else
                                        <span
                                            class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Başarısız</span>
                                    @endif
                                </td>
                                <td class="max-w-[250px] truncate px-3 py-3 text-xs text-gray-600">
                                    {{ $gorev->yanit_metni ?? '—' }}
                                </td>
                                <td class="px-3 py-3">
                                    @if ($gorev->ai_model)
                                        <span
                                            class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-600">{{ $gorev->ai_model }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center font-mono text-xs text-gray-500">
                                    {{ $gorev->deneme_sayisi }}
                                </td>
                                <td class="max-w-[150px] truncate px-3 py-3 text-xs text-red-500">
                                    {{ $gorev->hata_mesaji ?? '—' }}
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500">
                                    {{ $gorev->created_at->format('d.m.Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm text-gray-500">
                                    Henüz AI görevi bulunmuyor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($gorevler->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $gorevler->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
