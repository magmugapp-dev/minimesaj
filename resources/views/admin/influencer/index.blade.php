@extends('admin.layout.ana')

@section('baslik', 'AI Influencer Yönetimi')

@section('icerik')
    {{-- Özet Kartları --}}
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4 p-6 ">
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-2xl font-bold text-gray-900">{{ number_format($istatistikler['toplam']) }}</p>
            <p class="text-xs text-gray-500">Toplam Influencer</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-2xl font-bold text-green-600">{{ number_format($istatistikler['aktif']) }}</p>
            <p class="text-xs text-gray-500">Aktif Influencer</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-2xl font-bold text-blue-600">{{ number_format($istatistikler['bagli']) }}</p>
            <p class="text-xs text-gray-500">Instagram Bağlı</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow">
            <p class="text-2xl font-bold text-purple-600">{{ number_format($istatistikler['toplam_hesap']) }}</p>
            <p class="text-xs text-gray-500">Toplam IG Hesap</p>
        </div>
    </div>

    {{-- Üst Bar --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 p-6 ">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="text" name="arama" value="{{ request('arama') }}"
                placeholder="Ad, kullanıcı adı veya IG adı..."
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <select name="durum"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tüm Durumlar</option>
                <option value="aktif" {{ request('durum') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="pasif" {{ request('durum') === 'pasif' ? 'selected' : '' }}>Pasif</option>
                <option value="yasakli" {{ request('durum') === 'yasakli' ? 'selected' : '' }}>Yasaklı</option>
            </select>
            <select name="aktif"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">AI Durumu</option>
                <option value="1" {{ request('aktif') === '1' ? 'selected' : '' }}>AI Aktif</option>
                <option value="0" {{ request('aktif') === '0' ? 'selected' : '' }}>AI Pasif</option>
            </select>
            <button type="submit"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filtrele</button>
            @if (request()->hasAny(['arama', 'durum', 'aktif']))
                <a href="{{ route('admin.influencer.index') }}"
                    class="text-sm text-gray-500 hover:text-gray-700">Temizle</a>
            @endif
        </form>

        <a href="{{ route('admin.influencer.ekle') }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Yeni Influencer Ekle
        </a>
    </div>

    @if (session('basari'))
        <div class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-600">{{ session('basari') }}</div>
    @endif

    {{-- Tablo --}}
    <div class="p-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Influencer</th>
                    <th class="px-4 py-3">Kullanıcı Adı</th>
                    <th class="px-4 py-3">Instagram Hesapları</th>
                    <th class="px-4 py-3">AI Durumu</th>
                    <th class="px-4 py-3">Hesap Durumu</th>
                    <th class="px-4 py-3">Kayıt</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($influencerlar as $influencer)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $influencer->ad }} {{ $influencer->soyad }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $influencer->kullanici_adi }}</td>
                        <td class="px-4 py-3">
                            @foreach ($influencer->instagramHesaplari as $hesap)
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $hesap->aktif_mi ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ '@' . $hesap->instagram_kullanici_adi }}
                                </span>
                            @endforeach
                        </td>
                        <td class="px-4 py-3">
                            @if ($influencer->aiAyar?->aktif_mi)
                                <span
                                    class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aktif</span>
                            @else
                                <span
                                    class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Pasif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $renkler = ['aktif' => 'green', 'pasif' => 'yellow', 'yasakli' => 'red'];
                                $renk = $renkler[$influencer->hesap_durumu] ?? 'gray';
                            @endphp
                            <span
                                class="inline-flex rounded-full bg-{{ $renk }}-100 px-2 py-0.5 text-xs font-medium text-{{ $renk }}-700">
                                {{ ucfirst($influencer->hesap_durumu) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $influencer->created_at->format('d.m.Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.influencer.goster', $influencer) }}"
                                    class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Detay">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </a>
                                <a href="{{ route('admin.influencer.duzenle', $influencer) }}"
                                    class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-blue-600" title="Düzenle">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
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
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">Henüz AI Influencer eklenmemiş.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($influencerlar->hasPages())
        <div class="mt-4">{{ $influencerlar->links() }}</div>
    @endif
@endsection
