@extends('admin.layout.ana')

@section('baslik', $kullanici->ad . ' - Influencer Düzenle')

@section('icerik')
    @php
        $ayar = $kullanici->aiAyar;
        $igHesap = $kullanici->instagramHesaplari->first();
        $listeler = include resource_path('ai_kisilik_ton_stil_model_listeleri.php');
        $openAiModelleri = ['gpt-4.1-nano', 'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4o'];
        $anaModelSecenekleri = array_values(array_unique(array_filter(array_merge($openAiModelleri, [old('model_adi', $ayar->model_adi)]))));
        $tamAd = trim($kullanici->ad . ' ' . $kullanici->soyad);
        $sections = [
            ['id' => 'profil', 'label' => 'Kullanıcı'],
            ['id' => 'operasyon', 'label' => 'Operasyon'],
            ['id' => 'kisilik', 'label' => 'Kişilik'],
            ['id' => 'model', 'label' => 'Model'],
            ['id' => 'mesaj', 'label' => 'Mesaj'],
        ];
    @endphp

    <div class="studio studio--influencer">
        <div class="studio-grid studio-grid--edit">
            <form method="POST" action="{{ route('admin.influencer.guncelle', $kullanici) }}" class="studio-main"
                x-data="{ provider: '{{ old('saglayici_tipi', $ayar->saglayici_tipi ?? 'gemini') }}' }">
                @csrf
                @method('PUT')
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('admin.influencer.goster', $kullanici) }}" class="studio-button studio-button--ghost">Detaya don</a>
                    <h1 class="text-2xl font-semibold text-slate-950">{{ $tamAd }}</h1>
                </div>

                @include('admin.partials.form-hatalari')

                <section id="profil" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div><p class="studio-kicker">Profil</p><h3 class="studio-title">Kullanıcı</h3></div>
                    </div>
                    <div class="studio-form-grid studio-form-grid--2">
                        <div><label class="studio-label" for="ad">Ad</label><input id="ad" type="text" name="ad" value="{{ old('ad', $kullanici->ad) }}" required class="studio-input" placeholder="Örn. Burçin"></div>
                        <div><label class="studio-label" for="soyad">Soyad</label><input id="soyad" type="text" name="soyad" value="{{ old('soyad', $kullanici->soyad) }}" class="studio-input" placeholder="Örn. Evci"></div>
                        <div><label class="studio-label" for="hesap_durumu">Hesap durumu</label><select id="hesap_durumu" name="hesap_durumu" class="studio-select"><option value="aktif" @selected(old('hesap_durumu', $kullanici->hesap_durumu) === 'aktif')>Aktif</option><option value="pasif" @selected(old('hesap_durumu', $kullanici->hesap_durumu) === 'pasif')>Pasif</option><option value="yasakli" @selected(old('hesap_durumu', $kullanici->hesap_durumu) === 'yasakli')>Yasaklı</option></select></div>
                        <div><label class="studio-label" for="cinsiyet">Cinsiyet</label><select id="cinsiyet" name="cinsiyet" class="studio-select"><option value="kadin" @selected(old('cinsiyet', $kullanici->cinsiyet) === 'kadin')>Kadın</option><option value="erkek" @selected(old('cinsiyet', $kullanici->cinsiyet) === 'erkek')>Erkek</option><option value="belirtmek_istemiyorum" @selected(old('cinsiyet', $kullanici->cinsiyet) === 'belirtmek_istemiyorum')>Belirtmek istemiyorum</option></select></div>
                        <div><label class="studio-label" for="dogum_yili">Doğum yılı</label><input id="dogum_yili" type="number" name="dogum_yili" value="{{ old('dogum_yili', $kullanici->dogum_yili) }}" min="1950" max="{{ now()->year }}" class="studio-input" placeholder="1998"></div>
                    </div>
                    <div class="mt-5"><label class="studio-label" for="biyografi">Biyografi</label><textarea id="biyografi" name="biyografi" rows="4" class="studio-textarea" placeholder="Kısa profil özeti">{{ old('biyografi', $kullanici->biyografi) }}</textarea></div>
                </section>

                <section id="operasyon" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div><p class="studio-kicker">Operasyon</p><h3 class="studio-title">Giriş ve Instagram</h3></div>
                    </div>
                    <div class="studio-form-grid studio-form-grid--2">
                        <div><label class="studio-label" for="kullanici_adi_sabit">Giriş kullanıcı adı</label><input id="kullanici_adi_sabit" type="text" value="{{ $kullanici->kullanici_adi }}" disabled class="studio-input" placeholder="Kullanıcı adı"></div>
                        <div x-data="{ showPassword: false }">
                            <label class="studio-label" for="sifre">Yeni şifre</label>
                            <div class="relative">
                                <input id="sifre" x-bind:type="showPassword ? 'text' : 'password'" name="sifre" minlength="6" class="studio-input pr-12" placeholder="Boş bırakılırsa değişmez">
                                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-4 flex items-center text-slate-400 transition hover:text-slate-700">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div><label class="studio-label" for="instagram_kullanici_adi">Instagram kullanıcı adı</label><input id="instagram_kullanici_adi" type="text" name="instagram_kullanici_adi" value="{{ old('instagram_kullanici_adi', $igHesap?->instagram_kullanici_adi) }}" class="studio-input" placeholder="@kullaniciadi"></div>
                        <div><label class="studio-label" for="instagram_profil_id">Instagram profil ID</label><input id="instagram_profil_id" type="text" name="instagram_profil_id" value="{{ old('instagram_profil_id', $igHesap?->instagram_profil_id) }}" class="studio-input" placeholder="1784..."></div>
                    </div>
                    <div class="studio-toggle-grid studio-toggle-grid--3 mt-6">
                        <label class="studio-toggle"><input type="hidden" name="otomatik_cevap_aktif_mi" value="0"><div class="studio-toggle__row"><p class="studio-toggle__title">Otomatik cevap</p><input type="checkbox" name="otomatik_cevap_aktif_mi" value="1" @checked(old('otomatik_cevap_aktif_mi', $igHesap?->otomatik_cevap_aktif_mi)) class="studio-check"></div></label>
                        <label class="studio-toggle"><input type="hidden" name="yarim_otomatik_mod_aktif_mi" value="0"><div class="studio-toggle__row"><p class="studio-toggle__title">Yarı otomatik mod</p><input type="checkbox" name="yarim_otomatik_mod_aktif_mi" value="1" @checked(old('yarim_otomatik_mod_aktif_mi', $igHesap?->yarim_otomatik_mod_aktif_mi)) class="studio-check"></div></label>
                        <label class="studio-toggle"><input type="hidden" name="instagram_aktif_mi" value="0"><div class="studio-toggle__row"><p class="studio-toggle__title">Instagram aktif</p><input type="checkbox" name="instagram_aktif_mi" value="1" @checked(old('instagram_aktif_mi', $igHesap?->aktif_mi)) class="studio-check"></div></label>
                    </div>
                </section>

                <section id="kisilik" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div><p class="studio-kicker">Kişilik</p><h3 class="studio-title">Ses ve karakter</h3></div>
                    </div>
                    <div class="studio-toggle-grid studio-toggle-grid--2">
                        <label class="studio-toggle"><input type="hidden" name="aktif_mi" value="0"><div class="studio-toggle__row"><p class="studio-toggle__title">AI aktif</p><input type="checkbox" name="aktif_mi" value="1" @checked(old('aktif_mi', $ayar->aktif_mi)) class="studio-check"></div></label>
                    </div>
                    <div class="studio-form-grid studio-form-grid--2 mt-6">
                        <div><label class="studio-label" for="kisilik_tipi">Kişilik tipi</label><select id="kisilik_tipi" name="kisilik_tipi" class="studio-select"><option value="">Seçiniz...</option>@foreach ($listeler['kisilik_tipleri'] as $secenek)<option value="{{ $secenek }}" @selected(old('kisilik_tipi', $ayar->kisilik_tipi) === $secenek)>{{ $secenek }}</option>@endforeach</select></div>
                        <div><label class="studio-label" for="konusma_tonu">Konuşma tonu</label><select id="konusma_tonu" name="konusma_tonu" class="studio-select"><option value="">Seçiniz...</option>@foreach ($listeler['konusma_tonlari'] as $secenek)<option value="{{ $secenek }}" @selected(old('konusma_tonu', $ayar->konusma_tonu) === $secenek)>{{ $secenek }}</option>@endforeach</select></div>
                        <div class="md:col-span-2"><label class="studio-label" for="kisilik_aciklamasi">Kişilik açıklaması</label><textarea id="kisilik_aciklamasi" name="kisilik_aciklamasi" rows="4" class="studio-textarea" placeholder="Karakterin genel tavrını yazın">{{ old('kisilik_aciklamasi', $ayar->kisilik_aciklamasi) }}</textarea></div>
                        <div class="md:col-span-2"><label class="studio-label" for="konusma_stili">Konuşma stili</label><select id="konusma_stili" name="konusma_stili" class="studio-select"><option value="">Seçiniz...</option>@foreach ($listeler['konusma_stilleri'] as $secenek)<option value="{{ $secenek }}" @selected(old('konusma_stili', $ayar->konusma_stili) === $secenek)>{{ $secenek }}</option>@endforeach</select></div>
                    </div>
                    <div class="mt-6">@include('admin.partials.ai-seviye-kaydiricilari')</div>
                </section>

                <section id="model" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div><p class="studio-kicker">Model</p><h3 class="studio-title">Üretim modeli</h3></div>
                    </div>
                    <div class="studio-form-grid studio-form-grid--2">
                        <div><label class="studio-label" for="saglayici_tipi">Sağlayıcı</label><select id="saglayici_tipi" name="saglayici_tipi" class="studio-select" x-model="provider"><option value="gemini">Google Gemini</option><option value="openai">OpenAI</option></select></div>
                        <div>
                            <label class="studio-label" for="model_adi_openai">Model</label>
                            <input type="hidden" name="model_adi" value="gemini-2.5-flash" x-bind:disabled="provider !== 'gemini'">
                            <select id="model_adi_openai" name="model_adi" class="studio-select" x-bind:disabled="provider !== 'openai'">
                                @foreach ($anaModelSecenekleri as $model)
                                    <option value="{{ $model }}" @selected(old('model_adi', $ayar->model_adi) === $model)>{{ $model }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="studio-form-grid studio-form-grid--3 mt-6">
                        <div x-data="{ value: {{ old('temperature', $ayar->temperature ?? 0.9) }} }" class="studio-slider"><div class="studio-slider__top"><p class="studio-slider__title">Temperature</p><div class="studio-slider__value"><span x-text="value"></span></div></div><input type="range" name="temperature" min="0" max="2" step="0.1" x-model="value" class="studio-range"></div>
                        <div x-data="{ value: {{ old('top_p', $ayar->top_p ?? 0.95) }} }" class="studio-slider"><div class="studio-slider__top"><p class="studio-slider__title">Top P</p><div class="studio-slider__value"><span x-text="value"></span></div></div><input type="range" name="top_p" min="0" max="1" step="0.05" x-model="value" class="studio-range"></div>
                        <div><label class="studio-label" for="max_output_tokens">Maksimum çıktı token</label><input id="max_output_tokens" type="number" name="max_output_tokens" value="{{ old('max_output_tokens', $ayar->max_output_tokens ?? 1024) }}" min="64" max="8192" class="studio-input" placeholder="1024"></div>
                    </div>
                </section>

                <section id="mesaj" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div><p class="studio-kicker">Mesaj</p><h3 class="studio-title">Mesaj akışı</h3></div>
                    </div>
                    <div class="studio-form-grid studio-form-grid--4">
                        <div><label class="studio-label" for="minimum_cevap_suresi_saniye">Min. cevap süresi</label><input id="minimum_cevap_suresi_saniye" type="number" name="minimum_cevap_suresi_saniye" value="{{ old('minimum_cevap_suresi_saniye', $ayar->minimum_cevap_suresi_saniye) }}" min="0" class="studio-input" placeholder="30"></div>
                        <div><label class="studio-label" for="maksimum_cevap_suresi_saniye">Maks. cevap süresi</label><input id="maksimum_cevap_suresi_saniye" type="number" name="maksimum_cevap_suresi_saniye" value="{{ old('maksimum_cevap_suresi_saniye', $ayar->maksimum_cevap_suresi_saniye) }}" min="0" class="studio-input" placeholder="180"></div>
                        <div><label class="studio-label" for="mesaj_uzunlugu_min">Min. mesaj uzunluğu</label><input id="mesaj_uzunlugu_min" type="number" name="mesaj_uzunlugu_min" value="{{ old('mesaj_uzunlugu_min', $ayar->mesaj_uzunlugu_min) }}" min="1" class="studio-input" placeholder="12"></div>
                        <div><label class="studio-label" for="mesaj_uzunlugu_max">Maks. mesaj uzunluğu</label><input id="mesaj_uzunlugu_max" type="number" name="mesaj_uzunlugu_max" value="{{ old('mesaj_uzunlugu_max', $ayar->mesaj_uzunlugu_max) }}" min="1" class="studio-input" placeholder="280"></div>
                        <div><label class="studio-label" for="gunluk_konusma_limiti">Günlük konuşma limiti</label><input id="gunluk_konusma_limiti" type="number" name="gunluk_konusma_limiti" value="{{ old('gunluk_konusma_limiti', $ayar->gunluk_konusma_limiti) }}" min="0" class="studio-input" placeholder="60"></div>
                        <div><label class="studio-label" for="tek_kullanici_gunluk_mesaj_limiti">Tek kullanıcı limiti</label><input id="tek_kullanici_gunluk_mesaj_limiti" type="number" name="tek_kullanici_gunluk_mesaj_limiti" value="{{ old('tek_kullanici_gunluk_mesaj_limiti', $ayar->tek_kullanici_gunluk_mesaj_limiti) }}" min="0" class="studio-input" placeholder="12"></div>
                    </div>
                    <div class="studio-toggle-grid studio-toggle-grid--2 mt-6">
                        <label class="studio-toggle"><input type="hidden" name="ilk_mesaj_atar_mi" value="0"><div class="studio-toggle__row"><p class="studio-toggle__title">İlk mesaj atsın</p><input type="checkbox" name="ilk_mesaj_atar_mi" value="1" @checked(old('ilk_mesaj_atar_mi', $ayar->ilk_mesaj_atar_mi)) class="studio-check"></div></label>
                    </div>
                    <div class="mt-5"><label class="studio-label" for="ilk_mesaj_sablonu">İlk mesaj şablonu</label><textarea id="ilk_mesaj_sablonu" name="ilk_mesaj_sablonu" rows="4" class="studio-textarea" placeholder="Merhaba, nasılsın?">{{ old('ilk_mesaj_sablonu', $ayar->ilk_mesaj_sablonu) }}</textarea></div>
                </section>

                <section class="studio-card">
                    <div class="studio-actions">
                        <h3 class="studio-title">Kaydet</h3>
                        <div class="studio-actions__buttons">
                            <a href="{{ route('admin.influencer.goster', $kullanici) }}" class="studio-button studio-button--ghost">Vazgeç</a>
                            <button type="submit" class="studio-button studio-button--primary">Değişiklikleri kaydet</button>
                        </div>
                    </div>
                </section>
            </form>

            <aside class="studio-sidebar">
                <section class="studio-card">
                    <nav class="studio-nav mt-4">
                        @foreach ($sections as $section)
                            <a href="#{{ $section['id'] }}" class="studio-nav__link">
                                <span>{{ $section['label'] }}</span>
                                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                </svg>
                            </a>
                        @endforeach
                    </nav>
                </section>

                <section class="studio-card">
                    <div class="studio-meta mt-4">
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Instagram</p>
                            <p class="studio-meta__value">{{ $igHesap?->instagram_kullanici_adi ?: 'Bağlı değil' }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Oto Yanıt</p>
                            <p class="studio-meta__value">{{ $igHesap?->otomatik_cevap_aktif_mi ? 'Açık' : 'Kapalı' }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">AI</p>
                            <p class="studio-meta__value">{{ $ayar->aktif_mi ? 'Aktif' : 'Pasif' }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Durum</p>
                            <p class="studio-meta__value">{{ ucfirst($kullanici->hesap_durumu) }}</p>
                        </div>
                    </div>
                </section>

                <section class="studio-card">
                    <div class="studio-stack mt-4">
                        <a href="{{ route('admin.influencer.goster', $kullanici) }}" class="studio-linkcard">
                            <span>Detayı gör</span>
                            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                            </svg>
                        </a>
                        <a href="{{ route('admin.influencer.index') }}" class="studio-linkcard">
                            <span>Influencer listesi</span>
                            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                            </svg>
                        </a>
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
