@extends('admin.layout.ana')

@section('baslik', 'AI Yönetimi')

@section('icerik')
    {{-- Özet Kartları --}}
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4 p-6 ">
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam']) }}</p>
            <p class="text-xs text-gray-500">Toplam AI</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-2xl font-bold text-green-600">{{ number_format($istatistikler['aktif']) }}</p>
            <p class="text-xs text-gray-500">Aktif AI</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-2xl font-bold text-blue-600">{{ number_format($istatistikler['gemini']) }}</p>
            <p class="text-xs text-gray-500">Gemini</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-2xl font-bold text-purple-600">{{ number_format($istatistikler['openai']) }}</p>
            <p class="text-xs text-gray-500">OpenAI</p>
        </div>
    </div>

    {{-- Filtre + Toplu İşlem --}}
    <div class="mb-6 rounded-lg bg-white p-4 shadow ">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('admin.ai.index') }}" class="flex flex-1 flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none"
                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input type="text" name="arama" value="{{ request('arama') }}" placeholder="AI adı ara..."
                        class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>

                <select name="durum"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="">Tüm Durumlar</option>
                    <option value="aktif" {{ request('durum') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="pasif" {{ request('durum') === 'pasif' ? 'selected' : '' }}>Pasif</option>
                    <option value="yasakli" {{ request('durum') === 'yasakli' ? 'selected' : '' }}>Yasaklı</option>
                </select>

                <select name="aktif"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="">AI Durumu</option>
                    <option value="1" {{ request('aktif') === '1' ? 'selected' : '' }}>AI Aktif</option>
                    <option value="0" {{ request('aktif') === '0' ? 'selected' : '' }}>AI Pasif</option>
                </select>

                <select name="saglayici"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <option value="">Tüm Sağlayıcılar</option>
                    <option value="gemini" {{ request('saglayici') === 'gemini' ? 'selected' : '' }}>Gemini</option>
                    <option value="openai" {{ request('saglayici') === 'openai' ? 'selected' : '' }}>OpenAI</option>
                </select>

                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
                <a href="{{ route('admin.ai.index') }}"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Temizle</a>
            </form>

            {{-- Toplu işlem --}}
            <div class="flex flex-wrap gap-2" x-data>
                <a href="{{ route('admin.ai.ekle') }}"
                    class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    + Tekli Ekle
                </a>
                <a href="{{ route('admin.ai.json-ekle') }}"
                    class="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                    JSON Toplu Ekle
                </a>
                <form method="POST" action="{{ route('admin.ai.toplu-durum') }}">
                    @csrf
                    <input type="hidden" name="islem" value="aktif_et">
                    <button type="submit"
                        @click="if(!confirm('Tüm AI kullanıcıları aktifleştirmek istediğinize emin misiniz?')) $event.preventDefault()"
                        class="rounded-lg border border-green-200 px-3 py-2 text-sm font-medium text-green-600 hover:bg-green-50">
                        Tümünü Aktifleştir
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.ai.toplu-durum') }}">
                    @csrf
                    <input type="hidden" name="islem" value="pasif_et">
                    <button type="submit"
                        @click="if(!confirm('Tüm AI kullanıcıları pasifleştirmek istediğinize emin misiniz?')) $event.preventDefault()"
                        class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                        Tümünü Pasifleştir
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Tablo --}}
    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">AI
                            Kullanıcı</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            Sağlayıcı</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Kişilik
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">AI
                            Durumu</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Model
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Hesap
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">İşlem
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($aiKullanicilar as $ai)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-purple-100 text-sm font-bold text-purple-700">
                                        {{ mb_substr($ai->ad, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $ai->ad }}
                                            {{ $ai->soyad }}</p>
                                        <p class="text-xs text-gray-500">{{ '@' . $ai->kullanici_adi }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($ai->aiAyar?->saglayici_tipi === 'gemini')
                                    <span
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">Gemini</span>
                                @elseif ($ai->aiAyar?->saglayici_tipi === 'openai')
                                    <span
                                        class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">OpenAI</span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                {{ $ai->aiAyar?->kisilik_tipi ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($ai->aiAyar?->aktif_mi)
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Aktif
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span> Pasif
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs font-mono text-gray-500">
                                {{ $ai->aiAyar?->model_adi ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($ai->hesap_durumu === 'aktif')
                                    <span class="text-xs font-medium text-green-600">Aktif</span>
                                @elseif ($ai->hesap_durumu === 'pasif')
                                    <span class="text-xs font-medium text-yellow-600">Pasif</span>
                                @else
                                    <span class="text-xs font-medium text-red-600">Yasaklı</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.ai.goster', $ai) }}"
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
                                    <a href="{{ route('admin.ai.duzenle', $ai) }}"
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
                                        d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                </svg>
                                AI kullanıcı bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($aiKullanicilar->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">
                {{ $aiKullanicilar->links() }}
            </div>
        @endif

        <div class="border-t border-gray-100 bg-gray-50 px-4 py-2.5 text-xs text-gray-500">
            Toplam {{ number_format($aiKullanicilar->total()) }} AI kullanıcı
        </div>
    </div>
@endsection
