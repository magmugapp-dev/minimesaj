@extends('admin.layout.ana')

@section('baslik', 'Ödeme Detayı #' . $odeme->id)

@section('icerik')
    <div class="space-y-6 p-6">

        {{-- Üst Bar --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.finansal.odemeler') }}"
                    class="rounded-lg bg-gray-100 p-2 text-gray-600 hover:bg-gray-200">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Ödeme #{{ $odeme->id }}</h2>
                    <p class="text-sm text-gray-500">{{ $odeme->created_at->format('d.m.Y H:i:s') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($odeme->durum === 'basarili')
                    <span class="rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">Başarılı</span>
                @elseif ($odeme->durum === 'bekliyor')
                    <span class="rounded-full bg-yellow-100 px-3 py-1 text-sm font-medium text-yellow-800">Bekliyor</span>
                @elseif ($odeme->durum === 'iptal')
                    <span class="rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-800">İptal</span>
                @elseif ($odeme->durum === 'iade')
                    <span class="rounded-full bg-orange-100 px-3 py-1 text-sm font-medium text-orange-800">İade</span>
                @else
                    <span
                        class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-800">{{ ucfirst($odeme->durum) }}</span>
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Ödeme Bilgileri --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 lg:col-span-2">
                <h3 class="mb-4 text-sm font-semibold text-gray-900">Ödeme Bilgileri</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-medium text-gray-500">Tutar</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">₺{{ number_format($odeme->tutar, 2, ',', '.') }}
                        </p>
                        <p class="text-xs text-gray-400">{{ $odeme->para_birimi }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-medium text-gray-500">Platform</p>
                        <div class="mt-1 flex items-center gap-2">
                            @if ($odeme->platform === 'ios')
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-700">
                                    iOS — App Store
                                </span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700">
                                    Android — Google Play
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-medium text-gray-500">Ürün Kodu</p>
                        <p class="mt-1 font-mono text-sm font-semibold text-gray-900">{{ $odeme->urun_kodu }}</p>
                        <p class="text-xs text-gray-400">
                            {{ $odeme->urun_tipi === 'abonelik' ? 'Abonelik' : 'Tek Seferlik' }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-medium text-gray-500">İşlem Kodu</p>
                        <p class="mt-1 font-mono text-sm font-semibold text-gray-900">{{ $odeme->islem_kodu ?? '—' }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-medium text-gray-500">Doğrulama Durumu</p>
                        <div class="mt-1">
                            @if ($odeme->dogrulama_durumu === 'dogrulandi')
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Doğrulandı
                                </span>
                            @elseif ($odeme->dogrulama_durumu === 'bekliyor')
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-3 py-1 text-sm font-medium text-yellow-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Bekliyor
                                </span>
                            @elseif ($odeme->dogrulama_durumu === 'reddedildi')
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Reddedildi
                                </span>
                            @else
                                <span class="text-sm text-gray-600">{{ ucfirst($odeme->dogrulama_durumu) }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-xs font-medium text-gray-500">Mağaza Tipi</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">
                            {{ $odeme->magaza_tipi === 'app_store' ? 'App Store' : 'Google Play' }}
                        </p>
                    </div>
                </div>

                {{-- Zaman Bilgisi --}}
                <div class="mt-4 rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Oluşturulma</p>
                            <p class="text-sm text-gray-900">{{ $odeme->created_at->format('d.m.Y H:i:s') }}</p>
                            <p class="text-xs text-gray-400">{{ $odeme->created_at->diffForHumans() }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Son Güncelleme</p>
                            <p class="text-sm text-gray-900">{{ $odeme->updated_at->format('d.m.Y H:i:s') }}</p>
                            <p class="text-xs text-gray-400">{{ $odeme->updated_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kullanıcı Bilgisi --}}
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h3 class="mb-4 text-sm font-semibold text-gray-900">Kullanıcı</h3>
                    @if ($odeme->user)
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-lg font-bold text-indigo-600">
                                {{ mb_substr($odeme->user->ad, 0, 1) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.kullanicilar.goster', $odeme->user) }}"
                                    class="font-semibold text-indigo-600 hover:text-indigo-800">
                                    {{ $odeme->user->ad }} {{ $odeme->user->soyad }}
                                </a>
                                <p class="text-xs text-gray-400">{{ '@' }}{{ $odeme->user->kullanici_adi }}</p>
                                <p class="text-xs text-gray-400">{{ $odeme->user->email }}</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-gray-50 p-3 text-center">
                                <p class="text-xs text-gray-500">Puan</p>
                                <p class="text-lg font-bold text-gray-900">{{ number_format($odeme->user->mevcut_puan) }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3 text-center">
                                <p class="text-xs text-gray-500">Premium</p>
                                <p
                                    class="text-lg font-bold {{ $odeme->user->premium_aktif_mi ? 'text-amber-600' : 'text-gray-400' }}">
                                    {{ $odeme->user->premium_aktif_mi ? 'Aktif' : 'Pasif' }}
                                </p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">Kullanıcı silinmiş</p>
                    @endif
                </div>

                {{-- Kullanıcının Diğer Ödemeleri --}}
                @if ($kullaniciOdemeleri->isNotEmpty())
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <h3 class="mb-4 text-sm font-semibold text-gray-900">Diğer Ödemeleri</h3>
                        <div class="space-y-2">
                            @foreach ($kullaniciOdemeleri as $digerOdeme)
                                <a href="{{ route('admin.finansal.odeme-detay', $digerOdeme) }}"
                                    class="flex items-center justify-between rounded-lg border border-gray-100 p-3 hover:bg-gray-50">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            ₺{{ number_format($digerOdeme->tutar, 2, ',', '.') }}</p>
                                        <p class="text-xs text-gray-400">{{ $digerOdeme->urun_kodu }} ·
                                            {{ $digerOdeme->created_at->format('d.m.Y') }}</p>
                                    </div>
                                    @if ($digerOdeme->durum === 'basarili')
                                        <span
                                            class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Başarılı</span>
                                    @elseif ($digerOdeme->durum === 'bekliyor')
                                        <span
                                            class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">Bekliyor</span>
                                    @else
                                        <span
                                            class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">{{ ucfirst($digerOdeme->durum) }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
