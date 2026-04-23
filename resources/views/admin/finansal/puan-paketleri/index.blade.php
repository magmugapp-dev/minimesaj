@extends('admin.layout.ana')

@section('baslik', 'Puan Paketleri')

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Mobil satın alma paketleri</h2>
            </div>
            <a href="{{ route('admin.finansal.puan-paketleri.create') }}"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Yeni Paket
            </a>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-4 flex gap-2 border-b border-gray-200 pb-3">
                <a href="{{ route('admin.finansal.odemeler') }}"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">
                    Ödemeler
                </a>
                <a href="{{ route('admin.finansal.puan-hareketleri') }}"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">
                    Puan Hareketleri
                </a>
                <a href="{{ route('admin.finansal.puan-paketleri.index') }}"
                    class="rounded-lg bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700">
                    Puan Paketleri
                </a>
                <a href="{{ route('admin.finansal.abonelik-paketleri.index') }}"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">
                    Abonelik Paketleri
                </a>
                <a href="{{ route('admin.finansal.aboneler') }}"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">
                    Aboneler
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wider text-gray-500">
                            <th class="px-3 py-3">Kod</th>
                            <th class="px-3 py-3">Store ürünleri</th>
                            <th class="px-3 py-3">Puan</th>
                            <th class="px-3 py-3">Fiyat</th>
                            <th class="px-3 py-3">Durum</th>
                            <th class="px-3 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($paketler as $paket)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3">
                                    <p class="font-medium text-gray-900">{{ $paket->kod }}</p>
                                    <p class="text-xs text-gray-400">Sıra: {{ $paket->sira }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="text-gray-700">Android: {{ $paket->android_urun_kodu ?: '-' }}</p>
                                    <p class="text-gray-500">iOS: {{ $paket->ios_urun_kodu ?: '-' }}</p>
                                </td>
                                <td class="px-3 py-3 font-semibold text-gray-900">{{ $paket->puan }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-semibold text-gray-900">
                                        {{ number_format($paket->fiyat, 2, ',', '.') }} {{ $paket->para_birimi }}
                                    </p>
                                    @if ($paket->rozet)
                                        <p class="text-xs font-medium text-indigo-600">{{ $paket->rozet }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $paket->aktif ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $paket->aktif ? 'Aktif' : 'Pasif' }}
                                        </span>
                                        @if ($paket->onerilen_mi)
                                            <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                                                Önerilen
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.finansal.puan-paketleri.edit', $paket) }}"
                                            class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                            Düzenle
                                        </a>
                                        <form method="POST"
                                            action="{{ route('admin.finansal.puan-paketleri.destroy', $paket) }}"
                                            onsubmit="return confirm('Bu paketi silmek istediğine emin misin?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                                Sil
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-10 text-center text-sm text-gray-500">
                                    Henüz puan paketi tanımlanmadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
