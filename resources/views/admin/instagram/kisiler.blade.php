@extends('admin.layout.ana')

@section('baslik', 'Kisiler - @' . $instagramHesap->instagram_kullanici_adi)

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.instagram.goster', $instagramHesap) }}"
                class="rounded-lg bg-gray-100 p-2 text-gray-600 hover:bg-gray-200">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <div>
                <h2 class="text-lg font-bold text-gray-900">
                    {{ '@' }}{{ $instagramHesap->instagram_kullanici_adi }} - Kisiler</h2>
                <p class="text-sm text-gray-500">{{ $kisiler->total() }} kisi</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div class="min-w-[200px] flex-1">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Arama</label>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Kullanici adi, gorunen ad, notlar..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Ara</button>
                    <a href="{{ route('admin.instagram.kisiler', $instagramHesap) }}"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Temizle</a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500">
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Kisi</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-500">Mesajlar</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Son Mesaj</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Notlar</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">Islem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($kisiler as $kisi)
                            @php
                                $kisiKullaniciAdi = $kisi->instagram_kullanici_adi ?? $kisi->kullanici_adi;
                                $kisiGorunenAd = $kisi->gorunen_ad ?: $kisiKullaniciAdi;
                                $kisiProfilResmi = $kisi->profil_fotografi_url ?? $kisi->profil_resmi;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3">
                                    <a href="{{ route('admin.instagram.mesajlar', [$instagramHesap, $kisi]) }}"
                                        class="flex items-center gap-3">
                                        @if ($kisiProfilResmi)
                                            <img src="{{ $kisiProfilResmi }}" alt=""
                                                class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <div
                                                class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-500">
                                                {{ mb_strtoupper(mb_substr($kisiKullaniciAdi, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-medium text-indigo-700 hover:text-indigo-800">
                                                {{ $kisiGorunenAd }}
                                            </p>
                                            <p class="text-xs text-gray-400">{{ '@' }}{{ $kisiKullaniciAdi }}
                                            </p>
                                        </div>
                                    </a>
                                </td>
                                <td class="px-3 py-3 text-center font-medium text-gray-700">
                                    {{ number_format($kisi->mesajlar_count) }}
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500">
                                    @if ($kisi->son_mesaj_tarihi)
                                        {{ $kisi->son_mesaj_tarihi->format('d.m.Y H:i') }}
                                        <p class="text-gray-400">{{ $kisi->son_mesaj_tarihi->diffForHumans() }}</p>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="max-w-[200px] truncate px-3 py-3 text-xs text-gray-500">
                                    {{ $kisi->notlar ?? '-' }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('admin.instagram.mesajlar', [$instagramHesap, $kisi]) }}"
                                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                        </svg>
                                        Mesajlar
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">
                                    Henuz kisi bulunmuyor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($kisiler->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $kisiler->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
