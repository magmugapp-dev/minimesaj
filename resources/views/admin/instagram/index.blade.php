@extends('admin.layout.ana')

@section('baslik', 'Instagram Hesapları')

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- İstatistik Kartları --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-8">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-medium text-gray-500">Toplam Hesap</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam_hesap']) }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-medium text-green-600">Bağlı</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ number_format($istatistikler['bagli']) }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                <p class="text-xs font-medium text-red-600">Kopuk</p>
                <p class="mt-1 text-2xl font-bold text-red-700">{{ number_format($istatistikler['kopuk']) }}</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                <p class="text-xs font-medium text-violet-600">Oto-Yanıt</p>
                <p class="mt-1 text-2xl font-bold text-violet-700">{{ number_format($istatistikler['oto_yanit']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-medium text-amber-600">Yarı-Oto</p>
                <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($istatistikler['yari_oto']) }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-medium text-blue-600">Kişiler</p>
                <p class="mt-1 text-2xl font-bold text-blue-700">{{ number_format($istatistikler['toplam_kisi']) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-medium text-indigo-600">Mesajlar</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format($istatistikler['toplam_mesaj']) }}</p>
            </div>
            <div class="rounded-xl border border-pink-200 bg-pink-50 p-4">
                <p class="text-xs font-medium text-pink-600">AI Görev</p>
                <p class="mt-1 text-2xl font-bold text-pink-700">{{ number_format($istatistikler['ai_gorev']) }}</p>
            </div>
        </div>

        {{-- Filtreler --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Arama</label>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Instagram kullanıcı adı, kullanıcı..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Bağlantı</label>
                    <select name="durum"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="bagli" {{ request('durum') === 'bagli' ? 'selected' : '' }}>Bağlı</option>
                        <option value="kopuk" {{ request('durum') === 'kopuk' ? 'selected' : '' }}>Kopuk</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Oto-Yanıt</label>
                    <select name="oto_yanit"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="aktif" {{ request('oto_yanit') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="pasif" {{ request('oto_yanit') === 'pasif' ? 'selected' : '' }}>Pasif</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
                    <a href="{{ route('admin.instagram.index') }}"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Temizle</a>
                </div>
            </form>

            {{-- Tablo --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500">
                            <th class="px-3 py-3 text-left font-medium text-gray-500">#</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Instagram Hesabı</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Kullanıcı</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-500">Bağlantı</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-500">Modlar</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-500">Kişiler</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-500">Mesajlar</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-500">AI Görev</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tarih</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($hesaplar as $hesap)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3 font-mono text-xs text-gray-400">#{{ $hesap->id }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 via-pink-500 to-orange-400">
                                            <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">
                                                {{ '@' }}{{ $hesap->instagram_kullanici_adi }}</p>
                                            @if ($hesap->instagram_profil_id)
                                                <p class="font-mono text-xs text-gray-400">
                                                    {{ $hesap->instagram_profil_id }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($hesap->user)
                                        <a href="{{ route('admin.kullanicilar.goster', $hesap->user_id) }}"
                                            class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $hesap->user->ad }}
                                        </a>
                                        <p class="text-xs text-gray-400">
                                            {{ '@' }}{{ $hesap->user->kullanici_adi }}</p>
                                    @else
                                        <span class="text-gray-400">Silinmiş</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if ($hesap->aktif_mi)
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                            Bağlı
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                            Kopuk
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        @if ($hesap->otomatik_cevap_aktif_mi)
                                            <span
                                                class="rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-medium text-violet-700"
                                                title="Oto-Yanıt Aktif">OTO</span>
                                        @endif
                                        @if ($hesap->yarim_otomatik_mod_aktif_mi)
                                            <span
                                                class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700"
                                                title="Yarı-Oto Aktif">Y-O</span>
                                        @endif
                                        @if (!$hesap->otomatik_cevap_aktif_mi && !$hesap->yarim_otomatik_mod_aktif_mi)
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center font-medium text-gray-700">
                                    {{ number_format($hesap->kisiler_count) }}</td>
                                <td class="px-3 py-3 text-center font-medium text-gray-700">
                                    {{ number_format($hesap->mesajlar_count) }}</td>
                                <td class="px-3 py-3 text-center font-medium text-gray-700">
                                    {{ number_format($hesap->ai_gorevleri_count) }}</td>
                                <td class="px-3 py-3 text-xs text-gray-500">
                                    {{ $hesap->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('admin.instagram.goster', $hesap) }}"
                                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                        Detay
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-3 py-8 text-center text-sm text-gray-500">
                                    Henüz Instagram hesabı bulunmuyor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($hesaplar->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $hesaplar->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
