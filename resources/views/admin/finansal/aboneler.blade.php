@extends('admin.layout.ana')

@section('baslik', 'Aboneler')

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500">Toplam abonelik islemi</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($istatistikler['toplam_abone']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500">Aktif premium</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($istatistikler['aktif_premium']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500">Bu ay yeni abone</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($istatistikler['bu_ay_yeni_abone']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500">Abonelik geliri</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($istatistikler['abonelik_geliri'], 2, ',', '.') }} TL</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                <a href="{{ route('admin.finansal.odemeler') }}"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">
                    Odemeler
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
                    class="rounded-lg bg-indigo-100 px-4 py-2 text-sm font-medium text-indigo-700">
                    Aboneler
                </a>
            </div>

            <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-3">
                <input type="text" name="arama" value="{{ request('arama') }}" placeholder="Kullanici, email, urun kodu..."
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <select name="platform" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Tum platformlar</option>
                    <option value="android" @selected(request('platform') === 'android')>Android</option>
                    <option value="ios" @selected(request('platform') === 'ios')>iOS</option>
                </select>
                <button type="submit"
                    class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    Filtrele
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wider text-gray-500">
                            <th class="px-3 py-3">Kullanici</th>
                            <th class="px-3 py-3">Paket</th>
                            <th class="px-3 py-3">Platform</th>
                            <th class="px-3 py-3">Tutar</th>
                            <th class="px-3 py-3">Premium durumu</th>
                            <th class="px-3 py-3">Tarih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($abonelikler as $odeme)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3">
                                    <p class="font-medium text-gray-900">{{ $odeme->user?->ad }}</p>
                                    <p class="text-xs text-gray-500">{{ $odeme->user?->kullanici_adi }}</p>
                                    <p class="text-xs text-gray-400">{{ $odeme->user?->email }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-medium text-gray-900">{{ $odeme->urun_kodu }}</p>
                                    <p class="text-xs text-gray-500">{{ $odeme->islem_kodu ?: '-' }}</p>
                                </td>
                                <td class="px-3 py-3">{{ strtoupper($odeme->platform) }}</td>
                                <td class="px-3 py-3">
                                    {{ number_format($odeme->tutar, 2, ',', '.') }} {{ $odeme->para_birimi }}
                                </td>
                                <td class="px-3 py-3">
                                    @php
                                        $aktif = $odeme->user?->premium_aktif_mi &&
                                            (!$odeme->user?->premium_bitis_tarihi || $odeme->user?->premium_bitis_tarihi->isFuture());
                                    @endphp
                                    <div class="flex flex-col gap-1">
                                        <span
                                            class="inline-flex w-fit rounded-full px-2.5 py-0.5 text-xs font-medium {{ $aktif ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $aktif ? 'Aktif' : 'Pasif' }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            Bitis: {{ optional($odeme->user?->premium_bitis_tarihi)->format('d.m.Y H:i') ?: '-' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-600">{{ $odeme->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-10 text-center text-sm text-gray-500">
                                    Henuz basarili abonelik kaydi yok.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $abonelikler->links() }}
            </div>
        </div>
    </div>
@endsection
