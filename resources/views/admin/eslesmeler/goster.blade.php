@extends('admin.layout.ana')

@section('baslik', 'Eslesme Detayi #' . $eslesme->id)

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.eslesmeler.index') }}"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Eslesmeler
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-sm font-medium text-gray-700">#{{ $eslesme->id }}</span>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h3 class="mb-6 text-lg font-semibold text-gray-900">Eslesme #{{ $eslesme->id }}</h3>

                    <div class="flex items-center justify-center gap-6">
                        <div class="flex-1 text-center">
                            <div
                                class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 text-2xl font-bold text-indigo-600">
                                {{ $eslesme->user ? mb_substr($eslesme->user->ad, 0, 1) : '?' }}
                            </div>
                            @if ($eslesme->user)
                                <a href="{{ route('admin.eslesmeler.kisi-hafiza', [$eslesme, $eslesme->user]) }}"
                                    class="mt-2 block font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $eslesme->user->ad }} {{ $eslesme->user->soyad }}
                                </a>
                                <div class="mt-1 flex items-center justify-center gap-2 text-xs">
                                    <span class="text-gray-500">{{ $eslesme->user->email }}</span>
                                    <a href="{{ route('admin.kullanicilar.goster', $eslesme->user) }}"
                                        class="font-medium text-gray-500 hover:text-indigo-700">Profil</a>
                                </div>
                                <p class="mt-1 text-xs text-gray-400">{{ ucfirst($eslesme->user->hesap_tipi) }}</p>
                            @else
                                <p class="mt-2 text-sm text-gray-400">Silinmis</p>
                            @endif
                        </div>

                        <div class="flex flex-col items-center">
                            <svg class="h-10 w-10 text-pink-500" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                            </svg>
                            @if ($eslesme->durum === 'aktif')
                                <span
                                    class="mt-2 rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">Aktif</span>
                            @elseif ($eslesme->durum === 'bekliyor')
                                <span
                                    class="mt-2 rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800">Bekliyor</span>
                            @elseif ($eslesme->durum === 'bitti')
                                <span
                                    class="mt-2 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-800">Bitti</span>
                            @else
                                <span
                                    class="mt-2 rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-800">Iptal</span>
                            @endif
                        </div>

                        <div class="flex-1 text-center">
                            <div
                                class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-pink-100 text-2xl font-bold text-pink-600">
                                {{ $eslesme->eslesenUser ? mb_substr($eslesme->eslesenUser->ad, 0, 1) : '?' }}
                            </div>
                            @if ($eslesme->eslesenUser)
                                <a href="{{ route('admin.eslesmeler.kisi-hafiza', [$eslesme, $eslesme->eslesenUser]) }}"
                                    class="mt-2 block font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $eslesme->eslesenUser->ad }} {{ $eslesme->eslesenUser->soyad }}
                                </a>
                                <div class="mt-1 flex items-center justify-center gap-2 text-xs">
                                    <span class="text-gray-500">{{ $eslesme->eslesenUser->email }}</span>
                                    <a href="{{ route('admin.kullanicilar.goster', $eslesme->eslesenUser) }}"
                                        class="font-medium text-gray-500 hover:text-indigo-700">Profil</a>
                                </div>
                                <p class="mt-1 text-xs text-gray-400">{{ ucfirst($eslesme->eslesenUser->hesap_tipi) }}</p>
                            @else
                                <p class="mt-2 text-sm text-gray-400">Silinmis</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if (!empty($hafizaOzetleri))
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Kisi Bazli AI Hafizasi
                            </h4>
                        </div>
                        <div class="grid gap-4 xl:grid-cols-2">
                            @foreach ($hafizaOzetleri as $ozet)
                                @foreach ($ozet['paneller'] as $panel)
                                    @include('admin.partials.ai-hafiza-paneli', [
                                        'panel' => $panel,
                                        'compact' => true,
                                    ])
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Eslesme Bilgileri</h4>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Eslesme Turu</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">
                                @if ($eslesme->eslesme_turu === 'rastgele')
                                    <span
                                        class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">Rastgele</span>
                                @elseif ($eslesme->eslesme_turu === 'otomatik')
                                    <span
                                        class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-700">Otomatik</span>
                                @elseif ($eslesme->eslesme_turu === 'premium')
                                    <span
                                        class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">Premium</span>
                                @else
                                    <span
                                        class="rounded-full bg-teal-100 px-2.5 py-0.5 text-xs font-medium text-teal-700">Geri
                                        Donus</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Kaynak</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">
                                {{ $eslesme->eslesme_kaynagi === 'yapay_zeka' ? 'Yapay Zeka' : 'Gercek Kullanici' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Baslatan</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if ($eslesme->baslatanUser)
                                    <a href="{{ route('admin.kullanicilar.goster', $eslesme->baslatanUser) }}"
                                        class="text-indigo-600 hover:text-indigo-800">
                                        {{ $eslesme->baslatanUser->ad }} {{ $eslesme->baslatanUser->soyad }}
                                    </a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Tekrar Eslesebilir</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $eslesme->tekrar_eslesebilir_mi ? 'Evet' : 'Hayir' }}
                            </dd>
                        </div>
                        @if ($eslesme->bitis_sebebi)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium text-gray-500">Bitis Sebebi</dt>
                                <dd class="mt-1 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                    {{ $eslesme->bitis_sebebi }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Sohbet Bilgisi</h4>
                    @if ($sohbetBilgisi)
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Toplam Mesaj</dt>
                                <dd class="mt-1 text-2xl font-bold text-gray-900">
                                    {{ number_format($sohbetBilgisi['toplam_mesaj'] ?? 0) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Son Mesaj</dt>
                                <dd class="mt-1 text-sm font-medium text-gray-900">
                                    {{ $sohbetBilgisi['son_mesaj'] ? $sohbetBilgisi['son_mesaj']->format('d.m.Y H:i') : '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500">Sohbet Durumu</dt>
                                <dd class="mt-1 text-sm font-medium text-gray-900">
                                    {{ ucfirst($sohbetBilgisi['durum'] ?? '-') }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-sm text-gray-400">Bu eslesmeye henuz bir sohbet baslatilmamis.</p>
                    @endif
                </div>
</div>
</div>

            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Durumu Guncelle</h4>
                    <form method="POST" action="{{ route('admin.eslesmeler.durum-guncelle', $eslesme) }}">
                        @csrf
                        @method('PATCH')

                        <div class="space-y-4">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Durum</label>
                                <select name="durum"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="bekliyor" {{ $eslesme->durum === 'bekliyor' ? 'selected' : '' }}>
                                        Bekliyor</option>
                                    <option value="aktif" {{ $eslesme->durum === 'aktif' ? 'selected' : '' }}>Aktif
                                    </option>
                                    <option value="bitti" {{ $eslesme->durum === 'bitti' ? 'selected' : '' }}>Bitti
                                    </option>
                                    <option value="iptal" {{ $eslesme->durum === 'iptal' ? 'selected' : '' }}>Iptal
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Bitis Sebebi</label>
                                <textarea name="bitis_sebebi" rows="3" placeholder="Bitti veya iptal durumu icin..."
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('bitis_sebebi', $eslesme->bitis_sebebi) }}</textarea>
                                @error('bitis_sebebi')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Guncelle
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Hizli Islemler</h4>
                    <div class="space-y-2">
                        @if ($eslesme->user)
                            <a href="{{ route('admin.kullanicilar.goster', $eslesme->user) }}"
                                class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0" />
                                </svg>
                                {{ $eslesme->user->ad }} {{ $eslesme->user->soyad }} Profili
                            </a>
                        @endif
                        @if ($eslesme->eslesenUser)
                            <a href="{{ route('admin.kullanicilar.goster', $eslesme->eslesenUser) }}"
                                class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0" />
                                </svg>
                                {{ $eslesme->eslesenUser->ad }} {{ $eslesme->eslesenUser->soyad }} Profili
                            </a>
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Zaman Bilgisi</h4>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Eslesme Tarihi</dt>
                            <dd class="font-medium text-gray-900">{{ $eslesme->created_at->format('d.m.Y H:i:s') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Son Guncelleme</dt>
                            <dd class="font-medium text-gray-900">{{ $eslesme->updated_at->format('d.m.Y H:i:s') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Eslesme Suresi</dt>
                            <dd class="font-medium text-gray-900">{{ $eslesme->created_at->diffForHumans(null, true) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
