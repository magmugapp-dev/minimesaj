@extends('admin.layout.ana')

@section('baslik', $kullanici->kullanici_adi . ' — Detay')

@section('icerik')
    {{-- Üst başlık --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 p-6 ">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.kullanicilar.index') }}"
                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <div class="flex items-center gap-3">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full {{ $kullanici->cevrim_ici_mi ? 'bg-green-100 text-green-700 ring-2 ring-green-300' : 'bg-gray-100 text-gray-500' }} text-xl font-bold">
                    {{ mb_substr($kullanici->ad, 0, 1) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $kullanici->ad }} {{ $kullanici->soyad }}</h2>
                    <p class="text-sm text-gray-500">{{ '@' . $kullanici->kullanici_adi }} · #{{ $kullanici->id }}</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            {{-- Hızlı durum değiştirme --}}
            @if ($kullanici->id !== auth()->id())
                <form method="POST" action="{{ route('admin.kullanicilar.durum', $kullanici) }}" class="inline" x-data>
                    @csrf
                    @method('PATCH')
                    @if ($kullanici->hesap_durumu === 'aktif')
                        <input type="hidden" name="hesap_durumu" value="yasakli">
                        <button type="submit"
                            @click="if(!confirm('Bu kullanıcıyı yasaklamak istediğinize emin misiniz?')) $event.preventDefault()"
                            class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Yasakla
                        </button>
                    @elseif ($kullanici->hesap_durumu === 'yasakli')
                        <input type="hidden" name="hesap_durumu" value="aktif">
                        <button type="submit"
                            class="rounded-lg border border-green-200 px-3 py-2 text-sm font-medium text-green-600 hover:bg-green-50">
                            Yasağı Kaldır
                        </button>
                    @else
                        <input type="hidden" name="hesap_durumu" value="aktif">
                        <button type="submit"
                            class="rounded-lg border border-green-200 px-3 py-2 text-sm font-medium text-green-600 hover:bg-green-50">
                            Aktifleştir
                        </button>
                    @endif
                </form>
            @endif
            <a href="{{ route('admin.kullanicilar.duzenle', $kullanici) }}"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Düzenle
            </a>
        </div>
    </div>

    {{-- Bilgi Kartları --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 p-6 ">
        {{-- Sol Kolon: Profil --}}
        <div class="space-y-6 lg:col-span-1">
            {{-- Profil Bilgileri --}}
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Profil Bilgileri</h3>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">E-posta</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $kullanici->email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Hesap Tipi</dt>
                        <dd>
                            @if ($kullanici->hesap_tipi === 'ai')
                                <span
                                    class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-700">AI</span>
                            @else
                                <span
                                    class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">Gerçek</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Durum</dt>
                        <dd>
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
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Cinsiyet</dt>
                        <dd class="text-sm font-medium text-gray-900">
                            {{ match ($kullanici->cinsiyet) {'erkek' => 'Erkek','kadin' => 'Kadın',default => 'Belirtmek istemiyor'} }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Doğum Yılı</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $kullanici->dogum_yili ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Konum</dt>
                        <dd class="text-sm font-medium text-gray-900">
                            {{ collect([$kullanici->ilce, $kullanici->il, $kullanici->ulke])->filter()->implode(', ') ?:'—' }}
                        </dd>
                    </div>
                    @if ($kullanici->biyografi)
                        <div class="border-t border-gray-100 pt-3">
                            <dt class="mb-1 text-sm text-gray-500">Biyografi</dt>
                            <dd class="text-sm text-gray-700">{{ $kullanici->biyografi }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Hesap Bilgileri --}}
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Hesap Bilgileri</h3>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Kayıt Tarihi</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $kullanici->created_at->format('d.m.Y H:i') }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Son Görülme</dt>
                        <dd class="text-sm font-medium text-gray-900">
                            {{ $kullanici->son_gorulme_tarihi?->diffForHumans() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Çevrimiçi</dt>
                        <dd>
                            @if ($kullanici->cevrim_ici_mi)
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600">
                                    <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span> Çevrimiçi
                                </span>
                            @else
                                <span class="text-xs text-gray-400">Çevrimdışı</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">Admin</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $kullanici->is_admin ? 'Evet' : 'Hayır' }}</dd>
                    </div>
                    @if ($kullanici->google_kimlik)
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Google</dt>
                            <dd class="text-xs font-medium text-gray-500">Bağlı</dd>
                        </div>
                    @endif
                    @if ($kullanici->apple_kimlik)
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Apple</dt>
                            <dd class="text-xs font-medium text-gray-500">Bağlı</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Sağ Kolon: İstatistik + Detaylar --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- İstatistik Kartları --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($kullanici->mevcut_puan) }}</p>
                    <p class="text-xs text-gray-500">Puan</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $kullanici->eslesmeler_count }}</p>
                    <p class="text-xs text-gray-500">Eşleşme</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $kullanici->gelen_begeniler_count }}</p>
                    <p class="text-xs text-gray-500">Gelen Beğeni</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $kullanici->instagram_hesaplari_count }}</p>
                    <p class="text-xs text-gray-500">Instagram</p>
                </div>
            </div>

            {{-- Premium Bilgisi --}}
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Premium & Ekonomi</h3>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-gray-500">Premium</p>
                        <p class="text-sm font-medium text-gray-900">
                            @if ($kullanici->premium_aktif_mi)
                                <span class="text-amber-600">⭐ Aktif</span>
                                @if ($kullanici->premium_bitis_tarihi)
                                    <span
                                        class="text-xs text-gray-400">({{ $kullanici->premium_bitis_tarihi->format('d.m.Y') }})</span>
                                @endif
                            @else
                                Yok
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Günlük Ücretsiz Hak</p>
                        <p class="text-sm font-medium text-gray-900">{{ $kullanici->gunluk_ucretsiz_hak }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Toplam Ödeme</p>
                        <p class="text-sm font-medium text-gray-900">{{ $kullanici->odemeler_count }}</p>
                    </div>
                </div>
            </div>

            {{-- AI Ayarları (varsa) --}}
            @if ($kullanici->aiAyar)
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">AI Ayarları</h3>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs text-gray-500">Sağlayıcı</p>
                            <p class="text-sm font-medium text-gray-900">{{ $kullanici->aiAyar->aktif_saglayici }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Saat Dilimi</p>
                            <p class="text-sm font-medium text-gray-900">{{ $kullanici->aiAyar->saat_dilimi ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Cevap Gecikmesi</p>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $kullanici->aiAyar->min_cevap_gecikmesi }}–{{ $kullanici->aiAyar->max_cevap_gecikmesi }}s
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Son Ödemeler --}}
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">Son Ödemeler</h3>
                @if ($sonOdemeler->isEmpty())
                    <p class="text-sm text-gray-400">Henüz ödeme yok.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($sonOdemeler as $odeme)
                            <div class="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ number_format($odeme->tutar, 2, ',', '.') }} ₺</p>
                                    <p class="text-xs text-gray-500">{{ $odeme->urun_adi ?? $odeme->urun_kodu }} ·
                                        {{ $odeme->created_at->diffForHumans() }}</p>
                                </div>
                                @if ($odeme->durum === 'basarili')
                                    <span
                                        class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Başarılı</span>
                                @else
                                    <span
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ $odeme->durum }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Hakkında Şikayetler --}}
            <div class="rounded-lg bg-white p-6 shadow">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Hakkında Şikayetler</h3>
                    <span
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $hakkindaSikayetler->count() }}</span>
                </div>
                @if ($hakkindaSikayetler->isEmpty())
                    <p class="text-sm text-gray-400">Şikayet bulunmuyor.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($hakkindaSikayetler as $sikayet)
                            @php
                                $sikayetOzeti = $sikayet->aciklama
                                    ? \Illuminate\Support\Str::limit($sikayet->aciklama, 72)
                                    : ucfirst($sikayet->kategori);
                            @endphp
                            <div class="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $sikayet->sikayetEden?->kullanici_adi ?? '—' }}</p>
                                    <p class="text-xs text-gray-500">{{ $sikayetOzeti }} ·
                                        {{ $sikayet->created_at->diffForHumans() }}</p>
                                </div>
                                @if (in_array($sikayet->durum, ['beklemede', 'bekliyor'], true))
                                    <span
                                        class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700">Bekliyor</span>
                                @elseif (in_array($sikayet->durum, ['incelendi', 'inceleniyor'], true))
                                    <span
                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Inceleniyor</span>
                                @elseif (in_array($sikayet->durum, ['cozuldu', 'sonuclandi'], true))
                                    <span
                                        class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Cozuldu</span>
                                @else
                                    <span
                                        class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ $sikayet->durum }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
