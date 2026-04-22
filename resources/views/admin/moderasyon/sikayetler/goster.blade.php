@extends('admin.layout.ana')

@section('baslik', 'Şikayet Detayı #' . $sikayet->id)

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- Üst navigasyon --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.moderasyon.sikayetler') }}"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Şikayetler
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-sm font-medium text-gray-700">#{{ $sikayet->id }}</span>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Sol: Şikayet Detayı --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Şikayet bilgileri --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Şikayet #{{ $sikayet->id }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $sikayet->created_at->format('d.m.Y H:i') }} —
                                {{ $sikayet->created_at->diffForHumans() }}</p>
                        </div>
                        @if ($sikayet->durum === 'bekliyor')
                            <span
                                class="rounded-full bg-yellow-100 px-3 py-1 text-sm font-medium text-yellow-800">Bekliyor</span>
                        @elseif ($sikayet->durum === 'inceleniyor')
                            <span
                                class="rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">İnceleniyor</span>
                        @elseif ($sikayet->durum === 'cozuldu')
                            <span
                                class="rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">Çözüldü</span>
                        @else
                            <span
                                class="rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-800">Reddedildi</span>
                        @endif
                    </div>

                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Kategori</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">{{ ucfirst($sikayet->kategori) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Hedef Tipi</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900">
                                {{ $sikayet->hedef_tipi === 'user' ? 'Kullanıcı' : 'Mesaj' }} #{{ $sikayet->hedef_id }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium text-gray-500">Açıklama</dt>
                            <dd class="mt-1 text-sm text-gray-700">{{ $sikayet->aciklama ?: '—' }}</dd>
                        </div>
                        @if ($sikayet->yonetici_notu)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium text-gray-500">Yönetici Notu</dt>
                                <dd class="mt-1 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                                    {{ $sikayet->yonetici_notu }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Şikayet Eden --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Şikayet Eden</h4>
                    @if ($sikayet->sikayetEden)
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-lg font-bold text-indigo-600">
                                {{ mb_substr($sikayet->sikayetEden->ad, 0, 1) }}
                            </div>
                            <div>
                                <a href="{{ route('admin.kullanicilar.goster', $sikayet->sikayetEden) }}"
                                    class="font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $sikayet->sikayetEden->ad }} {{ $sikayet->sikayetEden->soyad }}
                                </a>
                                <p class="text-sm text-gray-500">{{ $sikayet->sikayetEden->email }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">Kullanıcı silinmiş.</p>
                    @endif
                </div>

                {{-- Hedef Bilgisi --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Şikayet Edilen
                        {{ $sikayet->hedef_tipi === 'user' ? 'Kullanıcı' : 'Mesaj' }}</h4>
                    @if ($hedef)
                        @if ($sikayet->hedef_tipi === 'user')
                            <div class="flex items-center gap-4">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-lg font-bold text-red-600">
                                    {{ mb_substr($hedef->ad, 0, 1) }}
                                </div>
                                <div>
                                    <a href="{{ route('admin.kullanicilar.goster', $hedef) }}"
                                        class="font-medium text-indigo-600 hover:text-indigo-800">
                                        {{ $hedef->ad }} {{ $hedef->soyad }}
                                    </a>
                                    <p class="text-sm text-gray-500">{{ $hedef->email }}</p>
                                    <p class="text-xs text-gray-400">Hesap tipi: {{ $hedef->hesap_tipi }} — Durum:
                                        {{ $hedef->hesap_durumu }}</p>
                                </div>
                            </div>
                        @else
                            <div class="rounded-lg bg-gray-50 p-4">
                                <p class="text-sm whitespace-pre-wrap break-words text-gray-700">
                                    {{ $hedef->mesaj_metni ?: 'Mesaj metni yok.' }}
                                </p>
                                <div class="mt-3 space-y-1 text-xs text-gray-500">
                                    <p>Mesaj Tipi: {{ $hedef->mesaj_tipi ?: '—' }}</p>
                                    @if ($hedef->sohbet)
                                        <p>Sohbet: #{{ $hedef->sohbet->id }}</p>
                                    @endif
                                </div>
                                @if ($hedef->gonderen)
                                    <p class="mt-2 text-xs text-gray-500">
                                        Gönderen:
                                        <a href="{{ route('admin.kullanicilar.goster', $hedef->gonderen) }}"
                                            class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $hedef->gonderen->ad }} {{ $hedef->gonderen->soyad }}
                                        </a>
                                    </p>
                                @endif
                                <p class="mt-1 text-xs text-gray-400">{{ $hedef->created_at?->format('d.m.Y H:i') }}</p>
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-gray-400">Hedef silinmiş veya bulunamadı.</p>
                    @endif
                </div>

                {{-- Benzer Şikayetler --}}
                @if ($benzerSikayetler->isNotEmpty())
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <h4 class="mb-4 text-sm font-semibold text-gray-900">Aynı Hedef Hakkında Diğer Şikayetler
                            ({{ $benzerSikayetler->count() }})</h4>
                        <div class="divide-y divide-gray-100">
                            @foreach ($benzerSikayetler as $benzer)
                                <div class="flex items-center justify-between py-3">
                                    <div>
                                        <a href="{{ route('admin.moderasyon.sikayetler.goster', $benzer) }}"
                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800">#{{ $benzer->id }}</a>
                                        <span class="ml-2 text-xs text-gray-500">{{ ucfirst($benzer->kategori) }}</span>
                                        @if ($benzer->sikayetEden)
                                            <span class="ml-2 text-xs text-gray-400">— {{ $benzer->sikayetEden->ad }}
                                                {{ $benzer->sikayetEden->soyad }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if ($benzer->durum === 'bekliyor')
                                            <span
                                                class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">Bekliyor</span>
                                        @elseif ($benzer->durum === 'inceleniyor')
                                            <span
                                                class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">İnceleniyor</span>
                                        @elseif ($benzer->durum === 'cozuldu')
                                            <span
                                                class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Çözüldü</span>
                                        @else
                                            <span
                                                class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Reddedildi</span>
                                        @endif
                                        <span
                                            class="text-xs text-gray-400">{{ $benzer->created_at->format('d.m.Y') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sağ: Durum Güncelleme --}}
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Durumu Güncelle</h4>
                    <form method="POST" action="{{ route('admin.moderasyon.sikayetler.durum-guncelle', $sikayet) }}">
                        @csrf
                        @method('PATCH')

                        <div class="space-y-4">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Durum</label>
                                <select name="durum"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="bekliyor" {{ $sikayet->durum === 'bekliyor' ? 'selected' : '' }}>
                                        Bekliyor</option>
                                    <option value="inceleniyor" {{ $sikayet->durum === 'inceleniyor' ? 'selected' : '' }}>
                                        İnceleniyor</option>
                                    <option value="cozuldu" {{ $sikayet->durum === 'cozuldu' ? 'selected' : '' }}>Çözüldü
                                    </option>
                                    <option value="reddedildi" {{ $sikayet->durum === 'reddedildi' ? 'selected' : '' }}>
                                        Reddedildi</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Yönetici Notu</label>
                                <textarea name="yonetici_notu" rows="4" placeholder="İnceleme notları..."
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('yonetici_notu', $sikayet->yonetici_notu) }}</textarea>
                                @error('yonetici_notu')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit"
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Güncelle
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Hızlı İşlemler --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Hızlı İşlemler</h4>
                    <div class="space-y-2">
                        @if ($sikayet->hedef_tipi === 'user' && $hedef)
                            <a href="{{ route('admin.kullanicilar.goster', $hedef) }}"
                                class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0" />
                                </svg>
                                Kullanıcıyı Görüntüle
                            </a>
                            <a href="{{ route('admin.kullanicilar.duzenle', $hedef) }}"
                                class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                </svg>
                                Kullanıcıyı Düzenle
                            </a>
                        @elseif ($sikayet->hedef_tipi === 'mesaj' && $hedef?->sohbet?->eslesme)
                            <a href="{{ route('admin.eslesmeler.sohbet', $hedef->sohbet->eslesme) }}"
                                class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-9 8.25h10.5a2.25 2.25 0 002.25-2.25V8.25A2.25 2.25 0 0017.25 6H6.75A2.25 2.25 0 004.5 8.25v7.5A2.25 2.25 0 006.75 18z" />
                                </svg>
                                Sohbeti Görüntüle
                            </a>
                        @endif
                        @if ($sikayet->sikayetEden)
                            <a href="{{ route('admin.kullanicilar.goster', $sikayet->sikayetEden) }}"
                                class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Şikayet Edeni Görüntüle
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Zaman çizelgesi --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Zaman Bilgisi</h4>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Oluşturulma</dt>
                            <dd class="font-medium text-gray-900">{{ $sikayet->created_at->format('d.m.Y H:i:s') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Son Güncelleme</dt>
                            <dd class="font-medium text-gray-900">{{ $sikayet->updated_at->format('d.m.Y H:i:s') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Bekleme Süresi</dt>
                            <dd class="font-medium text-gray-900">{{ $sikayet->created_at->diffForHumans(null, true) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
