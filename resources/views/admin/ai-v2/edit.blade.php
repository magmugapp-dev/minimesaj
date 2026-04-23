@extends('admin.layout.ana')

@section('baslik', 'AI Persona Editor')

@section('icerik')
    <form method="POST" action="{{ route('admin.ai.guncelle', $kullanici) }}" class="studio studio--ai space-y-6"
        x-data="{
            mizah: @js((int) old('mizah_seviyesi', $persona->mizah_seviyesi)),
            flort: @js((int) old('flort_seviyesi', $persona->flort_seviyesi)),
            emoji: @js((int) old('emoji_seviyesi', $persona->emoji_seviyesi)),
            giriskenlik: @js((int) old('giriskenlik_seviyesi', $persona->giriskenlik_seviyesi)),
            utangaclik: @js((int) old('utangaclik_seviyesi', $persona->utangaclik_seviyesi)),
            duygusallik: @js((int) old('duygusallik_seviyesi', $persona->duygusallik_seviyesi)),
            argo: @js((int) old('argo_seviyesi', $persona->argo_seviyesi ?? 2))
        }">
        @csrf
        @method('PUT')
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('admin.ai.goster', $kullanici) }}" class="studio-button studio-button--ghost">Detay Sayfasina Don</a>
            <h1 class="text-2xl font-semibold text-slate-950">{{ $kullanici->ad }} {{ $kullanici->soyad }}</h1>
        </div>

        @include('admin.ai-v2.partials.navigation')

        <div class="grid gap-6 xl:grid-cols-[1.08fr,1fr]">
            <section class="studio-card">
                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Calisma Modu</h2>
                    </div>
                </div>

                <div class="studio-toggle-grid studio-form-grid--2">
                    <label class="studio-toggle">
                        <div class="studio-toggle__row">
                            <div>
                                <div class="studio-toggle__title">Persona Aktif</div>
                            </div>
                            <input class="studio-check" type="checkbox" name="aktif_mi" value="1" @checked(old('aktif_mi', $persona->aktif_mi))>
                        </div>
                    </label>
                    <label class="studio-toggle">
                        <div class="studio-toggle__row">
                            <div>
                                <div class="studio-toggle__title">Dating Aktif</div>
                            </div>
                            <input class="studio-check" type="checkbox" name="dating_aktif_mi" value="1" @checked(old('dating_aktif_mi', $persona->dating_aktif_mi))>
                        </div>
                    </label>
                    <label class="studio-toggle">
                        <div class="studio-toggle__row">
                            <div>
                                <div class="studio-toggle__title">Instagram Aktif</div>
                            </div>
                            <input class="studio-check" type="checkbox" name="instagram_aktif_mi" value="1" @checked(old('instagram_aktif_mi', $persona->instagram_aktif_mi))>
                        </div>
                    </label>
                    <label class="studio-toggle">
                        <div class="studio-toggle__row">
                            <div>
                                <div class="studio-toggle__title">Ilk Mesaj Atar</div>
                            </div>
                            <input class="studio-check" type="checkbox" name="ilk_mesaj_atar_mi" value="1" @checked(old('ilk_mesaj_atar_mi', $persona->ilk_mesaj_atar_mi))>
                        </div>
                    </label>
                </div>
            </section>

            <section class="studio-card">
                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Persona Ozeti ve Ses</h2>
                    </div>
                </div>

                <div class="ai-studio-form-grid">
                    <label>
                        <span class="studio-label">Persona Ozeti</span>
                        <textarea class="studio-textarea" name="persona_ozeti" rows="6" placeholder="Karakterin enerjisini, dengesini ve kisa profilini anlat.">{{ old('persona_ozeti', $persona->persona_ozeti) }}</textarea>
                    </label>
                    <div class="studio-form-grid studio-form-grid--2">
                        <label>
                            <span class="studio-label">Ana Dil Kodu</span>
                            <input class="studio-input" type="text" name="ana_dil_kodu" value="{{ old('ana_dil_kodu', $persona->ana_dil_kodu ?? 'tr') }}" placeholder="en, tr, de...">
                        </label>
                        <label>
                            <span class="studio-label">Ana Dil Adi</span>
                            <input class="studio-input" type="text" name="ana_dil_adi" value="{{ old('ana_dil_adi', $persona->ana_dil_adi ?? 'Turkish') }}" placeholder="English">
                        </label>
                    </div>
                    <label>
                        <span class="studio-label">Ikinci Diller</span>
                        <input class="studio-input" type="text" name="ikinci_diller" value="{{ old('ikinci_diller', is_array($persona->ikinci_diller ?? null) ? implode(', ', $persona->ikinci_diller) : '') }}" placeholder="Turkish, German">
                    </label>
                    <div class="studio-form-grid studio-form-grid--2">
                        <label>
                            <span class="studio-label">Konusma Tonu</span>
                            <input class="studio-input" type="text" name="konusma_tonu" value="{{ old('konusma_tonu', $persona->konusma_tonu) }}" placeholder="dogal, yumusak, net...">
                        </label>
                        <label>
                            <span class="studio-label">Konusma Stili</span>
                            <input class="studio-input" type="text" name="konusma_stili" value="{{ old('konusma_stili', $persona->konusma_stili) }}" placeholder="samimi, kisa, akici...">
                        </label>
                    </div>
                    <label>
                        <span class="studio-label">Ilk Mesaj Tonu</span>
                        <textarea class="studio-textarea" name="ilk_mesaj_tonu" rows="4" placeholder="Eslesme acilisinda nasil bir enerji istedigini yaz.">{{ old('ilk_mesaj_tonu', $persona->ilk_mesaj_tonu) }}</textarea>
                    </label>
                </div>
            </section>
        </div>

        <section class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Kimlik ve Dil</h2>
                </div>
            </div>

            <div class="studio-form-grid studio-form-grid--4">
                <label>
                    <span class="studio-label">Ulke</span>
                    <input class="studio-input" type="text" name="persona_ulke" value="{{ old('persona_ulke', $persona->persona_ulke) }}">
                </label>
                <label>
                    <span class="studio-label">Bolge</span>
                    <input class="studio-input" type="text" name="persona_bolge" value="{{ old('persona_bolge', $persona->persona_bolge) }}">
                </label>
                <label>
                    <span class="studio-label">Sehir</span>
                    <input class="studio-input" type="text" name="persona_sehir" value="{{ old('persona_sehir', $persona->persona_sehir) }}">
                </label>
                <label>
                    <span class="studio-label">Yasam Cevresi</span>
                    <input class="studio-input" type="text" name="persona_mahalle" value="{{ old('persona_mahalle', $persona->persona_mahalle) }}">
                </label>
                <label>
                    <span class="studio-label">Kulturel Koken</span>
                    <input class="studio-input" type="text" name="kulturel_koken" value="{{ old('kulturel_koken', $persona->kulturel_koken) }}">
                </label>
                <label>
                    <span class="studio-label">Uyruk</span>
                    <input class="studio-input" type="text" name="uyruk" value="{{ old('uyruk', $persona->uyruk) }}">
                </label>
                <label>
                    <span class="studio-label">Yasam Tarzi</span>
                    <input class="studio-input" type="text" name="yasam_tarzi" value="{{ old('yasam_tarzi', $persona->yasam_tarzi) }}">
                </label>
                <label>
                    <span class="studio-label">Yas Araligi</span>
                    <input class="studio-input" type="text" name="yas_araligi" value="{{ old('yas_araligi', $persona->yas_araligi) }}" placeholder="24-28">
                </label>
            </div>
        </section>

        <section class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Yasam Detaylari</h2>
                </div>
            </div>

            <div class="studio-form-grid studio-form-grid--2">
                <label>
                    <span class="studio-label">Meslek</span>
                    <input class="studio-input" type="text" name="meslek" value="{{ old('meslek', $persona->meslek) }}">
                </label>
                <label>
                    <span class="studio-label">Sektor</span>
                    <input class="studio-input" type="text" name="sektor" value="{{ old('sektor', $persona->sektor) }}">
                </label>
                <label>
                    <span class="studio-label">Egitim</span>
                    <input class="studio-input" type="text" name="egitim" value="{{ old('egitim', $persona->egitim) }}">
                </label>
                <label>
                    <span class="studio-label">Okul / Bolum</span>
                    <input class="studio-input" type="text" name="okul_bolum" value="{{ old('okul_bolum', $persona->okul_bolum) }}">
                </label>
                <label>
                    <span class="studio-label">Gunluk Rutin</span>
                    <textarea class="studio-textarea" name="gunluk_rutin" rows="4">{{ old('gunluk_rutin', $persona->gunluk_rutin) }}</textarea>
                </label>
                <label>
                    <span class="studio-label">Hobiler</span>
                    <textarea class="studio-textarea" name="hobiler" rows="4">{{ old('hobiler', $persona->hobiler) }}</textarea>
                </label>
                <label>
                    <span class="studio-label">Sevdigi Mekanlar</span>
                    <textarea class="studio-textarea" name="sevdigi_mekanlar" rows="4">{{ old('sevdigi_mekanlar', $persona->sevdigi_mekanlar) }}</textarea>
                </label>
                <label>
                    <span class="studio-label">Aile / Arkadas Notu</span>
                    <textarea class="studio-textarea" name="aile_arkadas_notu" rows="4">{{ old('aile_arkadas_notu', $persona->aile_arkadas_notu) }}</textarea>
                </label>
            </div>
        </section>

        <section class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Konusma Gercekciligi</h2>
                </div>
            </div>

            <div class="studio-form-grid studio-form-grid--2">
                <label>
                    <span class="studio-label">Iliski Gecmisi Tonu</span>
                    <input class="studio-input" type="text" name="iliski_gecmisi_tonu" value="{{ old('iliski_gecmisi_tonu', $persona->iliski_gecmisi_tonu) }}">
                </label>
                <label>
                    <span class="studio-label">Cevap Ritmi</span>
                    <input class="studio-input" type="text" name="cevap_ritmi" value="{{ old('cevap_ritmi', $persona->cevap_ritmi) }}">
                </label>
                <label class="md:col-span-2">
                    <span class="studio-label">Konusma Imzasi</span>
                    <textarea class="studio-textarea" name="konusma_imzasi" rows="4">{{ old('konusma_imzasi', $persona->konusma_imzasi) }}</textarea>
                </label>
                <label>
                    <span class="studio-label">Emoji Aliskanligi</span>
                    <input class="studio-input" type="text" name="emoji_aliskanligi" value="{{ old('emoji_aliskanligi', $persona->emoji_aliskanligi) }}">
                </label>
                <label>
                    <span class="studio-label">Kacinilacak Detaylar</span>
                    <input class="studio-input" type="text" name="kacinilacak_persona_detaylari" value="{{ old('kacinilacak_persona_detaylari', $persona->kacinilacak_persona_detaylari) }}">
                </label>
            </div>
        </section>

        <section class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Davranis Sliderlari</h2>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="studio-slider">
                    <div class="studio-slider__top">
                        <div>
                            <div class="studio-slider__title">Mizah</div>
                        </div>
                        <div class="studio-slider__value" x-text="mizah"></div>
                    </div>
                    <input class="studio-range" type="range" min="0" max="10" name="mizah_seviyesi" x-model="mizah">
                    <div class="studio-range__legend"><span>Duz</span><span>Canli</span></div>
                </div>
                <div class="studio-slider">
                    <div class="studio-slider__top">
                        <div>
                            <div class="studio-slider__title">Flort</div>
                        </div>
                        <div class="studio-slider__value" x-text="flort"></div>
                    </div>
                    <input class="studio-range" type="range" min="0" max="10" name="flort_seviyesi" x-model="flort">
                    <div class="studio-range__legend"><span>Dusuk</span><span>Yuksek</span></div>
                </div>
                <div class="studio-slider">
                    <div class="studio-slider__top">
                        <div>
                            <div class="studio-slider__title">Emoji</div>
                        </div>
                        <div class="studio-slider__value" x-text="emoji"></div>
                    </div>
                    <input class="studio-range" type="range" min="0" max="10" name="emoji_seviyesi" x-model="emoji">
                    <div class="studio-range__legend"><span>Yok</span><span>Serbest</span></div>
                </div>
                <div class="studio-slider">
                    <div class="studio-slider__top">
                        <div>
                            <div class="studio-slider__title">Giriskenlik</div>
                        </div>
                        <div class="studio-slider__value" x-text="giriskenlik"></div>
                    </div>
                    <input class="studio-range" type="range" min="0" max="10" name="giriskenlik_seviyesi" x-model="giriskenlik">
                    <div class="studio-range__legend"><span>Pasif</span><span>One cikan</span></div>
                </div>
                <div class="studio-slider">
                    <div class="studio-slider__top">
                        <div>
                            <div class="studio-slider__title">Utangaclik</div>
                        </div>
                        <div class="studio-slider__value" x-text="utangaclik"></div>
                    </div>
                    <input class="studio-range" type="range" min="0" max="10" name="utangaclik_seviyesi" x-model="utangaclik">
                    <div class="studio-range__legend"><span>Acik</span><span>Cekingen</span></div>
                </div>
                <div class="studio-slider">
                    <div class="studio-slider__top">
                        <div>
                            <div class="studio-slider__title">Duygusallik</div>
                        </div>
                        <div class="studio-slider__value" x-text="duygusallik"></div>
                    </div>
                    <input class="studio-range" type="range" min="0" max="10" name="duygusallik_seviyesi" x-model="duygusallik">
                    <div class="studio-range__legend"><span>Net</span><span>Duygusal</span></div>
                </div>
                <div class="studio-slider">
                    <div class="studio-slider__top">
                        <div>
                            <div class="studio-slider__title">Argo</div>
                        </div>
                        <div class="studio-slider__value" x-text="argo"></div>
                    </div>
                    <input class="studio-range" type="range" min="0" max="10" name="argo_seviyesi" x-model="argo">
                    <div class="studio-range__legend"><span>Yok</span><span>Belirgin</span></div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[0.94fr,1.06fr]">
            <section class="studio-card">
                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Mesaj Boyu ve Zamanlama</h2>
                    </div>
                </div>

                <div class="studio-form-grid studio-form-grid--2">
                    <label>
                        <span class="studio-label">Mesaj Min</span>
                        <input class="studio-input" type="number" name="mesaj_uzunlugu_min" min="8" max="400" value="{{ old('mesaj_uzunlugu_min', $persona->mesaj_uzunlugu_min) }}">
                    </label>
                    <label>
                        <span class="studio-label">Mesaj Max</span>
                        <input class="studio-input" type="number" name="mesaj_uzunlugu_max" min="20" max="800" value="{{ old('mesaj_uzunlugu_max', $persona->mesaj_uzunlugu_max) }}">
                    </label>
                    <label>
                        <span class="studio-label">Cevap Min Saniye</span>
                        <input class="studio-input" type="number" name="minimum_cevap_suresi_saniye" min="0" max="600" value="{{ old('minimum_cevap_suresi_saniye', $persona->minimum_cevap_suresi_saniye) }}">
                    </label>
                    <label>
                        <span class="studio-label">Cevap Max Saniye</span>
                        <input class="studio-input" type="number" name="maksimum_cevap_suresi_saniye" min="0" max="1200" value="{{ old('maksimum_cevap_suresi_saniye', $persona->maksimum_cevap_suresi_saniye) }}">
                    </label>
                </div>

                <div class="studio-form-grid studio-form-grid--2 mt-6">
                    <label class="md:col-span-2">
                        <span class="studio-label">Saat Dilimi</span>
                        <input class="studio-input" type="text" name="saat_dilimi" value="{{ old('saat_dilimi', $persona->saat_dilimi) }}" placeholder="Europe/Istanbul">
                    </label>
                    <label>
                        <span class="studio-label">Uyku Baslangic</span>
                        <input class="studio-input" type="text" name="uyku_baslangic" value="{{ old('uyku_baslangic', $persona->uyku_baslangic) }}" placeholder="01:00">
                    </label>
                    <label>
                        <span class="studio-label">Uyku Bitis</span>
                        <input class="studio-input" type="text" name="uyku_bitis" value="{{ old('uyku_bitis', $persona->uyku_bitis) }}" placeholder="08:00">
                    </label>
                    <label>
                        <span class="studio-label">Hafta Sonu Baslangic</span>
                        <input class="studio-input" type="text" name="hafta_sonu_uyku_baslangic" value="{{ old('hafta_sonu_uyku_baslangic', $persona->hafta_sonu_uyku_baslangic) }}" placeholder="02:00">
                    </label>
                    <label>
                        <span class="studio-label">Hafta Sonu Bitis</span>
                        <input class="studio-input" type="text" name="hafta_sonu_uyku_bitis" value="{{ old('hafta_sonu_uyku_bitis', $persona->hafta_sonu_uyku_bitis) }}" placeholder="10:00">
                    </label>
                </div>
            </section>

            <section class="studio-card">
                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Kisisel Kurallar</h2>
                    </div>
                </div>

                <div class="ai-studio-form-grid">
                    <label>
                        <span class="studio-label">Persona Yasakli Konular</span>
                        <textarea class="studio-textarea" name="blocked_topics" rows="8" placeholder="Her satir bu persona icin ek bir yasakli konu.">{{ old('blocked_topics', $blockedTopicsText) }}</textarea>
                    </label>
                    <label>
                        <span class="studio-label">Persona Zorunlu Kurallar</span>
                        <textarea class="studio-textarea" name="required_rules" rows="8" placeholder="Her satir personaya zorunlu davranis notu ekler.">{{ old('required_rules', $requiredRulesText) }}</textarea>
                    </label>
                </div>
            </section>
        </div>

        <div class="ai-studio-sticky-actions">
            <div class="studio-card">
                <div class="studio-actions">
                    <div class="studio-actions__buttons">
                        <a href="{{ route('admin.ai.goster', $kullanici) }}" class="studio-button studio-button--ghost">Vazgec</a>
                        <button class="studio-button studio-button--primary">Degisiklikleri Kaydet</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection


