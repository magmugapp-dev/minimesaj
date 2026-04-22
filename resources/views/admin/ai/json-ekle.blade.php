@extends('admin.layout.ana')

@section('baslik', 'JSON ile Toplu AI Ekleme')

@section('icerik')
    <div class="mx-auto max-w-4xl">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('admin.ai.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <h2 class="text-lg font-semibold text-gray-900">JSON ile Toplu AI Ekleme</h2>
        </div>

        @if (session('hata'))
            <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ session('hata') }}</div>
        @endif

        @if (session('hatalar'))
            <div class="mb-4 rounded-lg bg-amber-50 p-3">
                <p class="mb-1 text-sm font-medium text-amber-700">Bazı kayıtlarda hata oluştu:</p>
                <ul class="list-inside list-disc text-xs text-amber-600 space-y-0.5">
                    @foreach (session('hatalar') as $hata)
                        <li>{{ $hata }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Şablon Referans --}}
        <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4" x-data="{ acik: false, kopyalandi: false }">
            <button @click="acik = !acik" class="flex w-full items-center justify-between text-left">
                <div>
                    <p class="text-sm font-semibold text-indigo-800">JSON Format Şablonu</p>
                    <p class="text-xs text-indigo-600">Şablona göre kendi verilerinizi hazırlayın</p>
                </div>
                <svg class="h-5 w-5 text-indigo-500 transition-transform" :class="acik && 'rotate-180'" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            <div x-show="acik" x-cloak class="mt-3">
                <pre class="overflow-x-auto rounded-lg bg-white p-3 text-xs text-gray-700 border border-indigo-100"><code>{{ $sablon }}</code></pre>
                <div class="mt-2 flex items-center gap-2">
                    <button type="button"
                        @click="navigator.clipboard.writeText(@js($sablon)).then(() => { kopyalandi = true; setTimeout(() => kopyalandi = false, 2000) })"
                        class="rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700">
                        Panoya Kopyala
                    </button>
                    <button type="button"
                        @click="document.getElementById('jsonAlani').value = @js($sablon); document.getElementById('jsonAlani').dispatchEvent(new Event('input'))"
                        class="rounded border border-indigo-300 bg-white px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50">
                        Düzenleyiciye Yapıştır
                    </button>
                    <span x-show="kopyalandi" x-transition.opacity
                        class="flex items-center gap-1 text-xs font-medium text-green-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Kopyalandı!
                    </span>
                </div>
            </div>
        </div>

        {{-- Alan Açıklamaları --}}
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4" x-data="{ acik: false }">
            <button @click="acik = !acik" class="flex w-full items-center justify-between text-left">
                <div>
                    <p class="text-sm font-semibold text-gray-700">Alan Açıklamaları</p>
                    <p class="text-xs text-gray-500">Tüm kullanılabilir alanları ve formatlarını görüntüleyin</p>
                </div>
                <svg class="h-5 w-5 text-gray-400 transition-transform" :class="acik && 'rotate-180'" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            <div x-show="acik" x-cloak class="mt-3 space-y-3">

                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-500">Kişisel Bilgiler</p>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-3">
                    <div><span class="font-medium text-red-600">ad *</span> <span class="text-gray-500">— İsim</span></div>
                    <div><span class="font-medium text-gray-700">soyad</span> <span class="text-gray-500">— Opsiyonel</span>
                    </div>
                    <div><span class="font-medium text-red-600">kullanici_adi *</span> <span class="text-gray-500">—
                            Benzersiz</span></div>
                    <div><span class="font-medium text-red-600">cinsiyet *</span> <span class="text-gray-500">— erkek /
                            kadin / belirtmek_istemiyorum</span></div>
                    <div><span class="font-medium text-gray-700">dogum_yili</span> <span class="text-gray-500">—
                            1950-{{ date('Y') }}</span></div>
                    <div><span class="font-medium text-gray-700">ulke</span> <span class="text-gray-500">— Opsiyonel</span>
                    </div>
                    <div><span class="font-medium text-gray-700">il</span> <span class="text-gray-500">— Opsiyonel</span>
                    </div>
                    <div><span class="font-medium text-gray-700">ilce</span> <span class="text-gray-500">— Opsiyonel</span>
                    </div>
                    <div><span class="font-medium text-gray-700">biyografi</span> <span class="text-gray-500">— Maks 1000
                            karakter</span></div>
                </div>

                <p class="text-[10px] font-semibold uppercase tracking-wider text-violet-500">AI Model</p>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-3">
                    <div><span class="font-medium text-gray-700">aktif_mi</span> <span class="text-gray-500">— true/false
                            (varsayılan: true)</span></div>
                    <!-- saglayici_tipi, model_adi, yedek_saglayici_tipi, yedek_model_adi alanları kaldırıldı, sadece Gemini kullanılacak -->
                    <div><span class="font-medium text-gray-700">temperature</span> <span class="text-gray-500">— 0-2
                            (varsayılan: 0.8)</span></div>
                    <div><span class="font-medium text-gray-700">top_p</span> <span class="text-gray-500">— 0-1 (varsayılan:
                            0.9)</span></div>
                    <div><span class="font-medium text-gray-700">max_output_tokens</span> <span class="text-gray-500">—
                            64-8192 (varsayılan: 1024)</span></div>
                </div>

                <p class="text-[10px] font-semibold uppercase tracking-wider text-pink-500">Kişilik</p>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-3">
                    <div><span class="font-medium text-gray-700">kisilik_tipi</span> <span class="text-gray-500">—
                            eglenceli, romantik...</span></div>
                    <div><span class="font-medium text-gray-700">kisilik_aciklamasi</span> <span class="text-gray-500">—
                            Karakter açıklaması</span></div>
                    <div><span class="font-medium text-gray-700">konusma_tonu</span> <span class="text-gray-500">— samimi,
                            resmi...</span></div>
                    <div><span class="font-medium text-gray-700">konusma_stili</span> <span class="text-gray-500">—
                            gunluk, edebi...</span></div>
                </div>

                <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-500">Seviye Ayarları (0-10)</p>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-4">
                    <div><span class="font-medium text-gray-700">emoji_seviyesi</span></div>
                    <div><span class="font-medium text-gray-700">flort_seviyesi</span></div>
                    <div><span class="font-medium text-gray-700">giriskenlik_seviyesi</span></div>
                    <div><span class="font-medium text-gray-700">utangaclik_seviyesi</span></div>
                    <div><span class="font-medium text-gray-700">duygusallik_seviyesi</span></div>
                    <div><span class="font-medium text-gray-700">kiskanclik_seviyesi</span></div>
                    <div><span class="font-medium text-gray-700">mizah_seviyesi</span></div>
                    <div><span class="font-medium text-gray-700">zeka_seviyesi</span></div>
                </div>

                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-500">Mesajlaşma</p>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-3">
                    <div><span class="font-medium text-gray-700">ilk_mesaj_atar_mi</span> <span class="text-gray-500">—
                            true/false</span></div>
                    <div><span class="font-medium text-gray-700">ilk_mesaj_sablonu</span> <span class="text-gray-500">—
                            İlk mesaj metni</span></div>
                    <div><span class="font-medium text-gray-700">gunluk_konusma_limiti</span> <span
                            class="text-gray-500">— Sayı</span></div>
                    <div><span class="font-medium text-gray-700">tek_kullanici_gunluk_mesaj_limiti</span> <span
                            class="text-gray-500">— Sayı</span></div>
                    <div><span class="font-medium text-gray-700">minimum_cevap_suresi_saniye</span> <span
                            class="text-gray-500">— Saniye</span></div>
                    <div><span class="font-medium text-gray-700">maksimum_cevap_suresi_saniye</span> <span
                            class="text-gray-500">— Saniye</span></div>
                    <div><span class="font-medium text-gray-700">ortalama_mesaj_uzunlugu</span> <span
                            class="text-gray-500">— Karakter</span></div>
                    <div><span class="font-medium text-gray-700">mesaj_uzunlugu_min</span> <span class="text-gray-500">—
                            Min karakter</span></div>
                    <div><span class="font-medium text-gray-700">mesaj_uzunlugu_max</span> <span class="text-gray-500">—
                            Max karakter</span></div>
                    <div><span class="font-medium text-gray-700">sesli_mesaj_gonderebilir_mi</span> <span
                            class="text-gray-500">— true/false</span></div>
                    <div><span class="font-medium text-gray-700">foto_gonderebilir_mi</span> <span class="text-gray-500">—
                            true/false</span></div>
                </div>

                <p class="text-[10px] font-semibold uppercase tracking-wider text-cyan-500">Zamanlama</p>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-3">
                    <div><span class="font-medium text-gray-700">saat_dilimi</span> <span class="text-gray-500">—
                            Europe/Istanbul</span></div>
                    <div><span class="font-medium text-gray-700">uyku_baslangic</span> <span class="text-gray-500">—
                            HH:MM</span></div>
                    <div><span class="font-medium text-gray-700">uyku_bitis</span> <span class="text-gray-500">—
                            HH:MM</span></div>
                    <div><span class="font-medium text-gray-700">hafta_sonu_uyku_baslangic</span> <span
                            class="text-gray-500">— HH:MM</span></div>
                    <div><span class="font-medium text-gray-700">hafta_sonu_uyku_bitis</span> <span
                            class="text-gray-500">— HH:MM</span></div>
                    <div><span class="font-medium text-gray-700">rastgele_gecikme_dakika</span> <span
                            class="text-gray-500">— Dakika</span></div>
                </div>

                <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-500">Sistem & Hafıza</p>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-3">
                    <div><span class="font-medium text-gray-700">sistem_komutu</span> <span class="text-gray-500">— Sistem
                            promptu</span></div>
                    <div><span class="font-medium text-gray-700">yasakli_konular</span> <span class="text-gray-500">—
                            ["konu1", "konu2"]</span></div>
                    <div><span class="font-medium text-gray-700">zorunlu_kurallar</span> <span class="text-gray-500">—
                            ["kural1", "kural2"]</span></div>
                    <div><span class="font-medium text-gray-700">hafiza_aktif_mi</span> <span class="text-gray-500">—
                            true/false</span></div>
                    <div><span class="font-medium text-gray-700">hafiza_seviyesi</span> <span class="text-gray-500">—
                            dusuk/orta/yuksek</span></div>
                    <div><span class="font-medium text-gray-700">kullaniciyi_hatirlar_mi</span> <span
                            class="text-gray-500">— true/false</span></div>
                    <div><span class="font-medium text-gray-700">iliski_seviyesi_takibi_aktif_mi</span> <span
                            class="text-gray-500">— true/false</span></div>
                    <div><span class="font-medium text-gray-700">puanlama_etiketi</span> <span class="text-gray-500">— A,
                            B, C...</span></div>
                </div>

                <p class="text-[10px] text-gray-400"><span class="text-red-500">*</span> ile işaretli alanlar zorunludur.
                    Diğer tüm alanlar opsiyoneldir.</p>
            </div>
        </div>

        {{-- JSON Editör --}}
        <form method="POST" action="{{ route('admin.ai.json-kaydet') }}" x-data="{ sayi: 0 }">
            @csrf
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <div class="mb-3 flex items-center justify-between">
                    <label class="text-sm font-semibold text-gray-700">JSON Verisi</label>
                    <span class="text-xs text-gray-400"><span x-text="sayi">0</span> kayıt algılandı</span>
                </div>
                <textarea id="jsonAlani" name="json_veri" rows="20"
                    placeholder="JSON formatında AI kullanıcı verilerini buraya yapıştırın..."
                    @input="try { const v = JSON.parse($el.value); sayi = Array.isArray(v) ? v.length : (typeof v === 'object' ? 1 : 0); } catch { sayi = 0; }"
                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 font-mono text-xs leading-relaxed focus:border-indigo-500 focus:ring-indigo-500">{{ old('json_veri') }}</textarea>
                @error('json_veri')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-4 flex items-center justify-between">
                <a href="{{ route('admin.ai.ekle') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Tek tek eklemek mi istiyorsunuz?
                </a>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    Toplu Oluştur
                </button>
            </div>
        </form>
    </div>
@endsection
