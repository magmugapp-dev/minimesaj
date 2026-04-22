@extends('admin.layout.ana')

@section('baslik', 'Kullanıcılar')

@section('icerik')
    {{-- Filtreler --}}
    <div class="mb-6 rounded-lg bg-white p-4 shadow" x-data="{ filtreAcik: {{ request()->hasAny(['arama', 'hesap_tipi', 'durum', 'premium']) ? 'true' : 'false' }} }">
        <div class="flex items-center justify-between">
            <form method="GET" action="{{ route('admin.kullanicilar.index') }}" class="flex flex-1 items-center gap-3">
                {{-- Gizli filtre alanları (arama sırasında filtreler korunsun) --}}
                @if (request('hesap_tipi'))
                    <input type="hidden" name="hesap_tipi" value="{{ request('hesap_tipi') }}">
                @endif
                @if (request('durum'))
                    <input type="hidden" name="durum" value="{{ request('durum') }}">
                @endif
                @if (request('premium'))
                    <input type="hidden" name="premium" value="{{ request('premium') }}">
                @endif

                <div class="relative flex-1 max-w-md">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none"
                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input type="text" name="arama" value="{{ request('arama') }}"
                        placeholder="Ad, kullanıcı adı veya e-posta ara..."
                        class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Ara
                </button>
            </form>
            <button @click="filtreAcik = !filtreAcik"
                class="ml-3 flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                </svg>
                Filtrele
            </button>
        </div>

        {{-- Detaylı filtreler --}}
        <div x-show="filtreAcik" x-collapse class="mt-4 border-t border-gray-100 pt-4">
            <form method="GET" action="{{ route('admin.kullanicilar.index') }}" class="flex flex-wrap items-end gap-4">
                @if (request('arama'))
                    <input type="hidden" name="arama" value="{{ request('arama') }}">
                @endif

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Hesap Tipi</label>
                    <select name="hesap_tipi"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="user" {{ request('hesap_tipi') === 'user' ? 'selected' : '' }}>Gerçek</option>
                        <option value="ai" {{ request('hesap_tipi') === 'ai' ? 'selected' : '' }}>AI</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Durum</label>
                    <select name="durum"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="aktif" {{ request('durum') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="pasif" {{ request('durum') === 'pasif' ? 'selected' : '' }}>Pasif</option>
                        <option value="yasakli" {{ request('durum') === 'yasakli' ? 'selected' : '' }}>Yasaklı</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Premium</label>
                    <select name="premium"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <option value="">Tümü</option>
                        <option value="1" {{ request('premium') === '1' ? 'selected' : '' }}>Evet</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                        Uygula
                    </button>
                    <a href="{{ route('admin.kullanicilar.index') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        Temizle
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tablo --}}
    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        @php
                            $simdikiSirala = request('sirala', 'created_at');
                            $simdikiYon = request('yon', 'desc');
                            $siraUrl = fn($alan) => request()->fullUrlWithQuery([
                                'sirala' => $alan,
                                'yon' => $simdikiSirala === $alan && $simdikiYon === 'asc' ? 'desc' : 'asc',
                            ]);
                            $siraIkon = function ($alan) use ($simdikiSirala, $simdikiYon) {
                                if ($simdikiSirala !== $alan) {
                                    return '';
                                }
                                return $simdikiYon === 'asc' ? '↑' : '↓';
                            };
                        @endphp
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <a href="{{ $siraUrl('kullanici_adi') }}" class="hover:text-gray-900">Kullanıcı
                                {{ $siraIkon('kullanici_adi') }}</a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <a href="{{ $siraUrl('hesap_tipi') }}" class="hover:text-gray-900">Tip
                                {{ $siraIkon('hesap_tipi') }}</a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <a href="{{ $siraUrl('hesap_durumu') }}" class="hover:text-gray-900">Durum
                                {{ $siraIkon('hesap_durumu') }}</a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <a href="{{ $siraUrl('mevcut_puan') }}" class="hover:text-gray-900">Puan
                                {{ $siraIkon('mevcut_puan') }}</a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Premium
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <a href="{{ $siraUrl('created_at') }}" class="hover:text-gray-900">Kayıt
                                {{ $siraIkon('created_at') }}</a>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">İşlem
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($kullanicilar as $kullanici)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-full {{ $kullanici->cevrim_ici_mi ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }} text-sm font-bold">
                                        {{ mb_substr($kullanici->ad, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $kullanici->ad }}
                                            {{ $kullanici->soyad }}</p>
                                        <p class="text-xs text-gray-500">{{ '@' . $kullanici->kullanici_adi }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($kullanici->hesap_tipi === 'ai')
                                    <span
                                        class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-700">AI</span>
                                @else
                                    <span
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">Gerçek</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($kullanici->hesap_durumu === 'aktif')
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Aktif
                                    </span>
                                @elseif ($kullanici->hesap_durumu === 'pasif')
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-yellow-500"></span> Pasif
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span> Yasaklı
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                {{ number_format($kullanici->mevcut_puan) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($kullanici->premium_aktif_mi)
                                    <span
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">⭐
                                        Premium</span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                {{ $kullanici->created_at->format('d.m.Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.kullanicilar.goster', $kullanici) }}"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600"
                                        title="Detay">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.kullanicilar.duzenle', $kullanici) }}"
                                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-indigo-600"
                                        title="Düzenle">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">
                                <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                                Kullanıcı bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($kullanicilar->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">
                {{ $kullanicilar->links() }}
            </div>
        @endif

        {{-- Toplamlar --}}
        <div class="border-t border-gray-100 bg-gray-50 px-4 py-2.5 text-xs text-gray-500">
            Toplam {{ number_format($kullanicilar->total()) }} kullanıcı
        </div>
    </div>
@endsection
