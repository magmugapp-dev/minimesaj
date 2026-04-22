@extends('admin.layout.ana')

@section('baslik', 'Eslesmeler')

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-7">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-medium text-gray-500">Toplam</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam']) }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                <p class="text-xs font-medium text-green-600">Aktif</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ number_format($istatistikler['aktif']) }}</p>
            </div>
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
                <p class="text-xs font-medium text-yellow-600">Bekliyor</p>
                <p class="mt-1 text-2xl font-bold text-yellow-700">{{ number_format($istatistikler['bekliyor']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs font-medium text-gray-500">Bitti</p>
                <p class="mt-1 text-2xl font-bold text-gray-700">{{ number_format($istatistikler['bitti']) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-medium text-indigo-600">Bugun</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format($istatistikler['bugun']) }}</p>
            </div>
            <div class="rounded-xl border border-pink-200 bg-pink-50 p-4">
                <p class="text-xs font-medium text-pink-600">Begeni</p>
                <p class="mt-1 text-2xl font-bold text-pink-700">{{ number_format($istatistikler['begeni']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-xs font-medium text-rose-600">Karsilikli</p>
                <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($istatistikler['karsilikli']) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <div class="min-w-[200px] flex-1">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Arama</label>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Ad, kullanici adi, e-posta..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Durum</label>
                    <select name="durum"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tumu</option>
                        <option value="aktif" {{ request('durum') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="bekliyor" {{ request('durum') === 'bekliyor' ? 'selected' : '' }}>Bekliyor</option>
                        <option value="bitti" {{ request('durum') === 'bitti' ? 'selected' : '' }}>Bitti</option>
                        <option value="iptal" {{ request('durum') === 'iptal' ? 'selected' : '' }}>Iptal</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Tur</label>
                    <select name="tur"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tumu</option>
                        <option value="rastgele" {{ request('tur') === 'rastgele' ? 'selected' : '' }}>Rastgele</option>
                        <option value="otomatik" {{ request('tur') === 'otomatik' ? 'selected' : '' }}>Otomatik</option>
                        <option value="premium" {{ request('tur') === 'premium' ? 'selected' : '' }}>Premium</option>
                        <option value="geri_donus" {{ request('tur') === 'geri_donus' ? 'selected' : '' }}>Geri Donus
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Kaynak</label>
                    <select name="kaynak"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tumu</option>
                        <option value="gercek_kullanici" {{ request('kaynak') === 'gercek_kullanici' ? 'selected' : '' }}>
                            Gercek</option>
                        <option value="yapay_zeka" {{ request('kaynak') === 'yapay_zeka' ? 'selected' : '' }}>Yapay Zeka
                        </option>
                    </select>
                </div>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
                @if (request()->hasAny(['arama', 'durum', 'tur', 'kaynak']))
                    <a href="{{ route('admin.eslesmeler.index') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Temizle</a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-3 py-3 text-left font-medium text-gray-500">ID</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Kullanici 1</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-500"></th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Kullanici 2</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tur</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Kaynak</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Durum</th>
                            <th class="px-3 py-3 text-left font-medium text-gray-500">Tarih</th>
                            <th class="px-3 py-3 text-right font-medium text-gray-500">Islem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($eslesmeler as $eslesme)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-3 font-mono text-xs text-gray-400">#{{ $eslesme->id }}</td>
                                <td class="px-3 py-3">
                                    @if ($eslesme->user)
                                        <a href="{{ route('admin.eslesmeler.kisi-hafiza', [$eslesme, $eslesme->user]) }}"
                                            class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $eslesme->user->ad }} {{ $eslesme->user->soyad }}
                                        </a>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                            <span
                                                class="text-gray-400">{{ '@' }}{{ $eslesme->user->kullanici_adi }}</span>
                                            <a href="{{ route('admin.kullanicilar.goster', $eslesme->user) }}"
                                                class="font-medium text-gray-500 hover:text-indigo-700">Profil</a>
                                        </div>
                                    @else
                                        <span class="text-gray-400">Silinmis</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <svg class="mx-auto h-5 w-5 text-pink-400" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                    </svg>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($eslesme->eslesenUser)
                                        <a href="{{ route('admin.eslesmeler.kisi-hafiza', [$eslesme, $eslesme->eslesenUser]) }}"
                                            class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $eslesme->eslesenUser->ad }} {{ $eslesme->eslesenUser->soyad }}
                                        </a>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                            <span
                                                class="text-gray-400">{{ '@' }}{{ $eslesme->eslesenUser->kullanici_adi }}</span>
                                            <a href="{{ route('admin.kullanicilar.goster', $eslesme->eslesenUser) }}"
                                                class="font-medium text-gray-500 hover:text-indigo-700">Profil</a>
                                        </div>
                                    @else
                                        <span class="text-gray-400">Silinmis</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($eslesme->eslesme_turu === 'rastgele')
                                        <span
                                            class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Rastgele</span>
                                    @elseif ($eslesme->eslesme_turu === 'otomatik')
                                        <span
                                            class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">Otomatik</span>
                                    @elseif ($eslesme->eslesme_turu === 'premium')
                                        <span
                                            class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Premium</span>
                                    @else
                                        <span
                                            class="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-medium text-teal-700">Geri
                                            Donus</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($eslesme->eslesme_kaynagi === 'yapay_zeka')
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                            </svg>
                                            AI
                                        </span>
                                    @else
                                        <span
                                            class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">Gercek</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($eslesme->durum === 'aktif')
                                        <span
                                            class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Aktif</span>
                                    @elseif ($eslesme->durum === 'bekliyor')
                                        <span
                                            class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">Bekliyor</span>
                                    @elseif ($eslesme->durum === 'bitti')
                                        <span
                                            class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">Bitti</span>
                                    @else
                                        <span
                                            class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Iptal</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-500">
                                    {{ $eslesme->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($eslesme->sohbet)
                                            <a href="{{ route('admin.eslesmeler.sohbet', $eslesme) }}"
                                                class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                                </svg>
                                                Sohbet Detayi
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-12 text-center text-gray-400">Eslesme bulunamadi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($eslesmeler->hasPages())
                <div class="mt-4 border-t border-gray-100 pt-4">
                    {{ $eslesmeler->links() }}
                </div>
            @endif
        </div>

        <div class="flex justify-end">
            <a href="{{ route('admin.eslesmeler.begeniler') }}"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4 text-pink-500" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                </svg>
                Begeni Listesi
            </a>
        </div>
    </div>
@endsection
