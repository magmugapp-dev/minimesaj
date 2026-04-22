@extends('admin.layout.ana')

@section('baslik', 'Ödemeler')

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- Gelir Kartları --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-medium text-emerald-600">Toplam Gelir</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700">
                    ₺{{ number_format($istatistikler['toplam_gelir'], 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-medium text-green-600">Bugün</p>
                <p class="mt-1 text-2xl font-bold text-green-700">
                    ₺{{ number_format($istatistikler['bugun_gelir'], 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
                <p class="text-xs font-medium text-teal-600">Bu Ay</p>
                <p class="mt-1 text-2xl font-bold text-teal-700">
                    ₺{{ number_format($istatistikler['bu_ay_gelir'], 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-medium text-blue-600">iOS Gelir</p>
                <p class="mt-1 text-2xl font-bold text-blue-700">₺{{ number_format($istatistikler['ios'], 2, ',', '.') }}
                </p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-medium text-green-600">Android Gelir</p>
                <p class="mt-1 text-2xl font-bold text-green-700">
                    ₺{{ number_format($istatistikler['android'], 2, ',', '.') }}</p>
            </div>
        </div>

        {{-- İşlem Kartları --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-medium text-gray-500">Toplam İşlem</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam_islem']) }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-medium text-green-600">Başarılı</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ number_format($istatistikler['basarili']) }}</p>
            </div>
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
                <p class="text-xs font-medium text-yellow-600">Bekliyor</p>
                <p class="mt-1 text-2xl font-bold text-yellow-700">{{ number_format($istatistikler['bekliyor']) }}</p>
            </div>
            <div class="rounded-xl border border-purple-200 bg-purple-50 p-4">
                <p class="text-xs font-medium text-purple-600">Abonelik</p>
                <p class="mt-1 text-2xl font-bold text-purple-700">{{ number_format($istatistikler['abonelik']) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-medium text-indigo-600">Tek Seferlik</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format($istatistikler['tek_seferlik']) }}</p>
            </div>
        </div>

        {{-- Filtreler --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Arama</label>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Kullanıcı adı, işlem kodu, ürün kodu..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Durum</label>
                    <select name="durum"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        @foreach ($durumlar as $d)
                            <option value="{{ $d }}" {{ request('durum') === $d ? 'selected' : '' }}>
                                {{ ucfirst($d) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Platform</label>
                    <select name="platform"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="ios" {{ request('platform') === 'ios' ? 'selected' : '' }}>iOS</option>
                        <option value="android" {{ request('platform') === 'android' ? 'selected' : '' }}>Android</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Ürün Tipi</label>
                    <select name="urun_tipi"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="tek_seferlik" {{ request('urun_tipi') === 'tek_seferlik' ? 'selected' : '' }}>Tek
                            Seferlik</option>
                        <option value="abonelik" {{ request('urun_tipi') === 'abonelik' ? 'selected' : '' }}>Abonelik
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Doğrulama</label>
                    <select name="dogrulama"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="bekliyor" {{ request('dogrulama') === 'bekliyor' ? 'selected' : '' }}>Bekliyor
                        </option>
                        <option value="dogrulandi" {{ request('dogrulama') === 'dogrulandi' ? 'selected' : '' }}>Doğrulandı
                        </option>
                        <option value="reddedildi" {{ request('dogrulama') === 'reddedildi' ? 'selected' : '' }}>Reddedildi
                        </option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
                    <a href="{{ route('admin.finansal.odemeler') }}"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Temizle</a>
                </div>
            </form>

            {{-- Sekme Navigasyonu --}}
            <div class="mb-4 flex gap-2 border-b border-gray-200 pb-3">
                <a href="{{ route('admin.finansal.odemeler') }}"
                    class="rounded-lg bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700">
                    Ödemeler
                </a>
                <a href="{{ route('admin.finansal.puan-hareketleri') }}"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">
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
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Ürün</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Platform</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">Tutar</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Durum</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Doğrulama</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tarih</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($odemeler as $odeme)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3 font-mono text-xs text-gray-400">#{{ $odeme->id }}</td>
                                <td class="px-3 py-3">
                                    @if ($odeme->user)
                                        <a href="{{ route('admin.kullanicilar.goster', $odeme->user_id) }}"
                                            class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $odeme->user->ad }}
                                        </a>
                                        <p class="text-xs text-gray-400">
                                            {{ '@' }}{{ $odeme->user->kullanici_adi }}</p>
                                    @else
                                        <span class="text-gray-400">Silinmiş</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-medium text-gray-900">{{ $odeme->urun_kodu }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $odeme->urun_tipi === 'abonelik' ? 'Abonelik' : 'Tek Seferlik' }}
                                    </p>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($odeme->platform === 'ios')
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z" />
                                            </svg>
                                            iOS
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M17.523 2.306l1.745 3.02a.254.254 0 01-.093.348.256.256 0 01-.35-.093l-1.766-3.06C15.704 3.2 14.12 3.587 12.395 3.587S9.086 3.2 7.73 2.521L5.965 5.58a.255.255 0 01-.35.093.254.254 0 01-.092-.348l1.744-3.02C4.508 3.907 2.605 6.891 2.363 10.4H22.43c-.243-3.51-2.146-6.493-4.905-8.094zM7.5 8.223a.778.778 0 110-1.556.778.778 0 010 1.556zm9.79 0a.778.778 0 110-1.556.778.778 0 010 1.556zM2.3 11.2h20.19v.8a10.97 10.97 0 01-3.21 7.76A10.93 10.93 0 0112.37 23a10.93 10.93 0 01-6.91-3.24A10.97 10.97 0 012.3 12v-.8z" />
                                            </svg>
                                            Android
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <span
                                        class="font-semibold text-gray-900">₺{{ number_format($odeme->tutar, 2, ',', '.') }}</span>
                                    <p class="text-xs text-gray-400">{{ $odeme->para_birimi }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($odeme->durum === 'basarili')
                                        <span
                                            class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Başarılı</span>
                                    @elseif ($odeme->durum === 'bekliyor')
                                        <span
                                            class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">Bekliyor</span>
                                    @elseif ($odeme->durum === 'iptal')
                                        <span
                                            class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">İptal</span>
                                    @elseif ($odeme->durum === 'iade')
                                        <span
                                            class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-orange-800">İade</span>
                                    @else
                                        <span
                                            class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">{{ ucfirst($odeme->durum) }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($odeme->dogrulama_durumu === 'dogrulandi')
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Doğrulandı
                                        </span>
                                    @elseif ($odeme->dogrulama_durumu === 'bekliyor')
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-yellow-600">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Bekliyor
                                        </span>
                                    @elseif ($odeme->dogrulama_durumu === 'reddedildi')
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-red-600">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Reddedildi
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">{{ ucfirst($odeme->dogrulama_durumu) }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500">
                                    {{ $odeme->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('admin.finansal.odeme-detay', $odeme) }}"
                                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                        Detay
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-sm text-gray-500">
                                    Henüz ödeme kaydı bulunmuyor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($odemeler->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $odemeler->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
