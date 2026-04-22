@extends('admin.layout.ana')

@section('baslik', 'Puan Hareketleri')

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- İstatistik Kartları --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-medium text-gray-500">Toplam Hareket</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam_hareket']) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-medium text-indigo-600">Bugün</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format($istatistikler['bugun_hareket']) }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-medium text-green-600">Toplam Kazanılan</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ number_format($istatistikler['toplam_kazanilan']) }}
                </p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="text-xs font-medium text-red-600">Toplam Harcanan</p>
                <p class="mt-1 text-2xl font-bold text-red-700">{{ number_format($istatistikler['toplam_harcanan']) }}</p>
            </div>
        </div>

        {{-- İşlem Tipi Kartları --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-center">
                <p class="text-xs font-medium text-amber-600">Reklam</p>
                <p class="mt-1 text-xl font-bold text-amber-700">{{ number_format($istatistikler['reklam']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center">
                <p class="text-xs font-medium text-emerald-600">Ödeme</p>
                <p class="mt-1 text-xl font-bold text-emerald-700">{{ number_format($istatistikler['odeme']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-center">
                <p class="text-xs font-medium text-rose-600">Harcama</p>
                <p class="mt-1 text-xl font-bold text-rose-700">{{ number_format($istatistikler['harcama']) }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-center">
                <p class="text-xs font-medium text-blue-600">Günlük Hak</p>
                <p class="mt-1 text-xl font-bold text-blue-700">{{ number_format($istatistikler['gunluk_hak']) }}</p>
            </div>
            <div class="rounded-xl border border-pink-200 bg-pink-50 p-3 text-center">
                <p class="text-xs font-medium text-pink-600">Hediye</p>
                <p class="mt-1 text-xl font-bold text-pink-700">{{ number_format($istatistikler['hediye']) }}</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-3 text-center">
                <p class="text-xs font-medium text-violet-600">Yönetici</p>
                <p class="mt-1 text-xl font-bold text-violet-700">{{ number_format($istatistikler['yonetici']) }}</p>
            </div>
        </div>

        {{-- Filtreler --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Arama</label>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Kullanıcı adı, açıklama..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">İşlem Tipi</label>
                    <select name="islem_tipi"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="reklam" {{ request('islem_tipi') === 'reklam' ? 'selected' : '' }}>Reklam</option>
                        <option value="odeme" {{ request('islem_tipi') === 'odeme' ? 'selected' : '' }}>Ödeme</option>
                        <option value="harcama" {{ request('islem_tipi') === 'harcama' ? 'selected' : '' }}>Harcama
                        </option>
                        <option value="gunluk_hak" {{ request('islem_tipi') === 'gunluk_hak' ? 'selected' : '' }}>Günlük
                            Hak</option>
                        <option value="hediye" {{ request('islem_tipi') === 'hediye' ? 'selected' : '' }}>Hediye</option>
                        <option value="yonetici" {{ request('islem_tipi') === 'yonetici' ? 'selected' : '' }}>Yönetici
                        </option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
                    <a href="{{ route('admin.finansal.puan-hareketleri') }}"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Temizle</a>
                </div>
            </form>

            {{-- Sekme Navigasyonu --}}
            <div class="mb-4 flex gap-2 border-b border-gray-200 pb-3">
                <a href="{{ route('admin.finansal.odemeler') }}"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">
                    Ödemeler
                </a>
                <a href="{{ route('admin.finansal.puan-hareketleri') }}"
                    class="rounded-lg bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700">
                    Puan Hareketleri
                </a>
                <a href="{{ route('admin.finansal.puan-paketleri.index') }}"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">
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

            {{-- Tablo --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500">
                            <th class="px-3 py-3 text-left font-medium text-gray-500">#</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Kullanıcı</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">İşlem Tipi</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">Miktar</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">Bakiye</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Açıklama</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Referans</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tarih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($puanHareketleri as $hareket)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3 font-mono text-xs text-gray-400">#{{ $hareket->id }}</td>
                                <td class="px-3 py-3">
                                    @if ($hareket->user)
                                        <a href="{{ route('admin.kullanicilar.goster', $hareket->user_id) }}"
                                            class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $hareket->user->ad }}
                                        </a>
                                        <p class="text-xs text-gray-400">
                                            {{ '@' }}{{ $hareket->user->kullanici_adi }}</p>
                                    @else
                                        <span class="text-gray-400">Silinmiş</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($hareket->islem_tipi === 'reklam')
                                        <span
                                            class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Reklam</span>
                                    @elseif ($hareket->islem_tipi === 'odeme')
                                        <span
                                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Ödeme</span>
                                    @elseif ($hareket->islem_tipi === 'harcama')
                                        <span
                                            class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Harcama</span>
                                    @elseif ($hareket->islem_tipi === 'gunluk_hak')
                                        <span
                                            class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Günlük
                                            Hak</span>
                                    @elseif ($hareket->islem_tipi === 'hediye')
                                        <span
                                            class="rounded-full bg-pink-100 px-2 py-0.5 text-xs font-medium text-pink-700">Hediye</span>
                                    @else
                                        <span
                                            class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">Yönetici</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    @if ($hareket->puan_miktari > 0)
                                        <span
                                            class="font-semibold text-green-600">+{{ number_format($hareket->puan_miktari) }}</span>
                                    @else
                                        <span
                                            class="font-semibold text-red-600">{{ number_format($hareket->puan_miktari) }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <span class="text-xs text-gray-400">{{ number_format($hareket->onceki_bakiye) }}</span>
                                    <svg class="mx-1 inline h-3 w-3 text-gray-300" fill="none" viewBox="0 0 24 24"
                                        stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                    <span
                                        class="text-xs font-medium text-gray-700">{{ number_format($hareket->sonraki_bakiye) }}</span>
                                </td>
                                <td class="max-w-[200px] truncate px-3 py-3 text-xs text-gray-600">
                                    {{ $hareket->aciklama ?? '—' }}
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-400">
                                    @if ($hareket->referans_tipi)
                                        <span
                                            class="font-mono">{{ $hareket->referans_tipi }}:{{ $hareket->referans_id }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500">
                                    {{ $hareket->created_at->format('d.m.Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm text-gray-500">
                                    Henüz puan hareketi bulunmuyor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($puanHareketleri->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $puanHareketleri->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
