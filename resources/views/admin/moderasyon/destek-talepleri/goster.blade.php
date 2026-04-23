@extends('admin.layout.ana')

@section('baslik', 'Destek Talebi #' . $destekTalebi->id)

@section('icerik')
    <div class="space-y-6 p-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.moderasyon.destek-talepleri') }}"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Destek Talepleri
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-sm font-medium text-gray-700">#{{ $destekTalebi->id }}</span>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Destek Talebi #{{ $destekTalebi->id }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $destekTalebi->created_at->format('d.m.Y H:i') }} —
                                {{ $destekTalebi->created_at->diffForHumans() }}</p>
                        </div>
                        @if ($destekTalebi->durum === 'yeni')
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">Yeni</span>
                        @elseif ($destekTalebi->durum === 'inceleniyor')
                            <span
                                class="rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">İnceleniyor</span>
                        @else
                            <span
                                class="rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">Çözüldü</span>
                        @endif
                    </div>

                    <div>
                        <dt class="text-xs font-medium text-gray-500">Mesaj</dt>
                        <dd
                            class="mt-2 whitespace-pre-wrap wrap-break-word rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                            {{ $destekTalebi->mesaj }}</dd>
                    </div>

                    @if ($destekTalebi->yonetici_notu)
                        <div class="mt-4">
                            <dt class="text-xs font-medium text-gray-500">Yönetici Notu</dt>
                            <dd
                                class="mt-2 whitespace-pre-wrap rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                {{ $destekTalebi->yonetici_notu }}
                            </dd>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Talebi Oluşturan Kullanıcı</h4>
                    @if ($destekTalebi->user)
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-lg font-bold text-indigo-600">
                                {{ mb_substr($destekTalebi->user->ad, 0, 1) }}
                            </div>
                            <div>
                                <a href="{{ route('admin.kullanicilar.goster', $destekTalebi->user) }}"
                                    class="font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $destekTalebi->user->ad }} {{ $destekTalebi->user->soyad }}
                                </a>
                                <p class="text-sm text-gray-500">{{ $destekTalebi->user->email }}</p>
                                <p class="text-xs text-gray-400">Kullanıcı ID: #{{ $destekTalebi->user->id }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">Kullanıcı silinmiş.</p>
                    @endif
                </div>

                @if ($benzerTalepler->isNotEmpty())
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <h4 class="mb-4 text-sm font-semibold text-gray-900">Aynı Kullanıcıdan Diğer Talepler</h4>
                        <div class="divide-y divide-gray-100">
                            @foreach ($benzerTalepler as $benzerTalep)
                                <div class="flex items-center justify-between py-3">
                                    <div>
                                        <a href="{{ route('admin.moderasyon.destek-talepleri.goster', $benzerTalep) }}"
                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800">#{{ $benzerTalep->id }}</a>
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ \Illuminate\Support\Str::limit($benzerTalep->mesaj, 100) }}</p>
                                    </div>
                                    <span
                                        class="text-xs text-gray-400">{{ $benzerTalep->created_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold text-gray-900">Cevap Geçmişi</h4>
                        <span class="text-xs text-gray-400">{{ $destekTalebi->yanitlar->count() }} kayıt</span>
                    </div>

                    @if ($destekTalebi->yanitlar->isNotEmpty())
                        <div class="space-y-4">
                            @foreach ($destekTalebi->yanitlar as $yanit)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $yanit->admin?->ad }} {{ $yanit->admin?->soyad }}
                                            </p>
                                            <p class="text-xs text-gray-400">{{ $yanit->created_at->format('d.m.Y H:i') }}
                                            </p>
                                        </div>
                                        @if ($yanit->admin)
                                            <a href="{{ route('admin.kullanicilar.goster', $yanit->admin) }}"
                                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Profili
                                                aç</a>
                                        @endif
                                    </div>
                                    <p class="whitespace-pre-wrap wrap-break-word text-sm text-gray-700">
                                        {{ $yanit->mesaj }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400">Henüz eklenmiş bir yönetici yanıtı yok.</p>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Durum Güncelle</h4>
                    <form method="POST"
                        action="{{ route('admin.moderasyon.destek-talepleri.durum-guncelle', $destekTalebi) }}"
                        class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Durum</label>
                            <select name="durum"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="yeni" {{ $destekTalebi->durum === 'yeni' ? 'selected' : '' }}>Yeni
                                </option>
                                <option value="inceleniyor" {{ $destekTalebi->durum === 'inceleniyor' ? 'selected' : '' }}>
                                    İnceleniyor</option>
                                <option value="cozuldu" {{ $destekTalebi->durum === 'cozuldu' ? 'selected' : '' }}>Çözüldü
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Yönetici Notu</label>
                            <textarea name="yonetici_notu" rows="5"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="İç not ekleyin...">{{ old('yonetici_notu', $destekTalebi->yonetici_notu) }}</textarea>
                        </div>
                        <button type="submit"
                            class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Kaydet</button>
                    </form>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Yanıt Ekle</h4>
                    <form method="POST"
                        action="{{ route('admin.moderasyon.destek-talepleri.yanit-ekle', $destekTalebi) }}"
                        class="space-y-4">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Yanıt</label>
                            <textarea name="mesaj" rows="5"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Kullanıcıyla paylaşılan ya da dahili takip notu olarak tutulacak yanıtı yazın...">{{ old('mesaj') }}</textarea>
                        </div>
                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3">
                            <input type="checkbox" name="kullaniciya_eposta_gonder" value="1"
                                {{ old('kullaniciya_eposta_gonder', '1') ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="block text-sm font-medium text-gray-800">Kullanıcıya e-posta gönder</span>
                            </span>
                        </label>
                        <button type="submit"
                            class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-black">Yanıtı
                            Kaydet</button>
                    </form>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6">
                    <h4 class="mb-4 text-sm font-semibold text-gray-900">Özet</h4>
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500">Talep ID</dt>
                            <dd class="font-mono text-xs text-gray-700">#{{ $destekTalebi->id }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500">Kullanıcı</dt>
                            <dd class="text-right text-gray-700">{{ $destekTalebi->user?->ad }}
                                {{ $destekTalebi->user?->soyad }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500">E-posta</dt>
                            <dd class="text-right text-gray-700">{{ $destekTalebi->user?->email ?: '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500">Yanıt Sayısı</dt>
                            <dd class="text-right text-gray-700">{{ $destekTalebi->yanitlar->count() }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
