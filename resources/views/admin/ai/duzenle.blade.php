@extends('admin.layout.ana')

@section('baslik', $kullanici->ad . ' - AI Düzenle')

@section('icerik')
    @php
        $ayar = $kullanici->aiAyar;
        $listeler = include resource_path('ai_kisilik_ton_stil_model_listeleri.php');
        $openAiModelleri = ['gpt-4.1-nano', 'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4o'];
        $anaModelSecenekleri = array_values(
            array_unique(array_filter(array_merge($openAiModelleri, [old('model_adi', $ayar->model_adi)]))),
        );
        $yedekModelSecenekleri = array_values(
            array_unique(array_filter(array_merge($openAiModelleri, [old('yedek_model_adi', $ayar->yedek_model_adi)]))),
        );
        $tamAd = trim($kullanici->ad . ' ' . $kullanici->soyad);
        $sections = [
            ['id' => 'profil', 'label' => 'Kullanıcı'],
            ['id' => 'kisilik', 'label' => 'Kişilik'],
            ['id' => 'model', 'label' => 'Model'],
            ['id' => 'mesaj', 'label' => 'Mesaj'],
            ['id' => 'zamanlama', 'label' => 'Zamanlama'],
            ['id' => 'kurallar', 'label' => 'Kurallar'],
        ];
    @endphp

    <div class="studio studio--ai p-6">
        <div class="studio-grid studio-grid--edit">
            <form method="POST" action="{{ route('admin.ai.guncelle', $kullanici) }}" class="studio-main"
                x-data="{ provider: '{{ old('saglayici_tipi', $ayar->saglayici_tipi ?? 'gemini') }}', backupProvider: '{{ old('yedek_saglayici_tipi', $ayar->yedek_saglayici_tipi ?? '') }}' }">
                @csrf
                @method('PUT')

                <section class="studio-hero">
                    <div class="studio-hero__inner">
                        <div>
                            <a href="{{ route('admin.ai.goster', $kullanici) }}" class="studio-back">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                                </svg>
                                Detaya dön
                            </a>
                            <p class="studio-eyebrow">AI Editor</p>
                            <h2 class="studio-heading">{{ $tamAd }}</h2>
                            <div class="studio-chipbar">
                                <span class="studio-chip">{{ '@' . $kullanici->kullanici_adi }}</span>
                                <span class="studio-chip">{{ ucfirst($kullanici->hesap_durumu) }}</span>
                                <span class="studio-chip">{{ $ayar->aktif_mi ? 'AI aktif' : 'AI pasif' }}</span>
                            </div>
                        </div>

                        <div class="studio-panelstack">
                            <div class="studio-panel">
                                <p class="studio-panel__meta">Ana model</p>
                                <p class="studio-panel__title">{{ $ayar->model_adi ?? 'Tanımsız' }}</p>
                            </div>
                            <div class="studio-panel">
                                <p class="studio-panel__meta">Yedek model</p>
                                <p class="studio-panel__title">{{ $ayar->yedek_model_adi ?: 'Yok' }}</p>
                            </div>
                            <div class="studio-panel">
                                <p class="studio-panel__meta">Hafıza</p>
                                <p class="studio-panel__title">{{ $ayar->hafiza_aktif_mi ? 'Aktif' : 'Pasif' }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                @include('admin.partials.form-hatalari')

                <section id="profil" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div>
                            <p class="studio-kicker">Profil</p>
                            <h3 class="studio-title">Kullanıcı</h3>
                        </div>
                    </div>
                    <div class="studio-form-grid studio-form-grid--2">
                        <div><label class="studio-label" for="ad">Ad</label><input id="ad" type="text"
                                name="ad" value="{{ old('ad', $kullanici->ad) }}" required class="studio-input"
                                placeholder="Örn. Burçin"></div>
                        <div><label class="studio-label" for="soyad">Soyad</label><input id="soyad" type="text"
                                name="soyad" value="{{ old('soyad', $kullanici->soyad) }}" class="studio-input"
                                placeholder="Örn. Evci"></div>
                        <div><label class="studio-label" for="hesap_durumu">Hesap durumu</label><select id="hesap_durumu"
                                name="hesap_durumu" class="studio-select">
                                <option value="aktif" @selected(old('hesap_durumu', $kullanici->hesap_durumu) === 'aktif')>Aktif</option>
                                <option value="pasif" @selected(old('hesap_durumu', $kullanici->hesap_durumu) === 'pasif')>Pasif</option>
                                <option value="yasakli" @selected(old('hesap_durumu', $kullanici->hesap_durumu) === 'yasakli')>Yasaklı</option>
                            </select></div>
                        <div><label class="studio-label" for="cinsiyet">Cinsiyet</label><select id="cinsiyet"
                                name="cinsiyet" class="studio-select">
                                <option value="kadin" @selected(old('cinsiyet', $kullanici->cinsiyet) === 'kadin')>Kadın</option>
                                <option value="erkek" @selected(old('cinsiyet', $kullanici->cinsiyet) === 'erkek')>Erkek</option>
                                <option value="belirtmek_istemiyorum" @selected(old('cinsiyet', $kullanici->cinsiyet) === 'belirtmek_istemiyorum')>Belirtmek istemiyorum
                                </option>
                            </select></div>
                        <div><label class="studio-label" for="dogum_yili">Doğum yılı</label><input id="dogum_yili"
                                type="number" name="dogum_yili" value="{{ old('dogum_yili', $kullanici->dogum_yili) }}"
                                min="1950" max="{{ now()->year }}" class="studio-input" placeholder="1998"></div>
                    </div>
                    <div class="mt-5"><label class="studio-label" for="biyografi">Biyografi</label>
                        <textarea id="biyografi" name="biyografi" rows="4" class="studio-textarea" placeholder="Kısa profil özeti">{{ old('biyografi', $kullanici->biyografi) }}</textarea>
                    </div>
                </section>

                <section id="kisilik" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div>
                            <p class="studio-kicker">Kişilik</p>
                            <h3 class="studio-title">Ses ve karakter</h3>
                        </div>
                    </div>
                    <div class="studio-toggle-grid studio-toggle-grid--2">
                        <label class="studio-toggle"><input type="hidden" name="aktif_mi" value="0">
                            <div class="studio-toggle__row">
                                <p class="studio-toggle__title">AI aktif</p><input type="checkbox" name="aktif_mi"
                                    value="1" @checked(old('aktif_mi', $ayar->aktif_mi)) class="studio-check">
                            </div>
                        </label>
                        <label class="studio-toggle"><input type="hidden" name="hafiza_aktif_mi" value="0">
                            <div class="studio-toggle__row">
                                <p class="studio-toggle__title">Hafıza aktif</p><input type="checkbox"
                                    name="hafiza_aktif_mi" value="1" @checked(old('hafiza_aktif_mi', $ayar->hafiza_aktif_mi))
                                    class="studio-check">
                            </div>
                        </label>
                    </div>
                    <div class="studio-form-grid studio-form-grid--2 mt-6">
                        <div><label class="studio-label" for="kisilik_tipi">Kişilik tipi</label><select id="kisilik_tipi"
                                name="kisilik_tipi" class="studio-select">
                                <option value="">Seçiniz...</option>
                                @foreach ($listeler['kisilik_tipleri'] as $secenek)
                                    <option value="{{ $secenek }}" @selected(old('kisilik_tipi', $ayar->kisilik_tipi) === $secenek)>{{ $secenek }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="studio-label" for="konusma_tonu">Konuşma tonu</label><select id="konusma_tonu"
                                name="konusma_tonu" class="studio-select">
                                <option value="">Seçiniz...</option>
                                @foreach ($listeler['konusma_tonlari'] as $secenek)
                                    <option value="{{ $secenek }}" @selected(old('konusma_tonu', $ayar->konusma_tonu) === $secenek)>{{ $secenek }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2"><label class="studio-label" for="kisilik_aciklamasi">Kişilik
                                açıklaması</label>
                            <textarea id="kisilik_aciklamasi" name="kisilik_aciklamasi" rows="4" class="studio-textarea"
                                placeholder="Karakterin genel tavrını yazın">{{ old('kisilik_aciklamasi', $ayar->kisilik_aciklamasi) }}</textarea>
                        </div>
                        <div class="md:col-span-2"><label class="studio-label" for="konusma_stili">Konuşma
                                stili</label><select id="konusma_stili" name="konusma_stili" class="studio-select">
                                <option value="">Seçiniz...</option>
                                @foreach ($listeler['konusma_stilleri'] as $secenek)
                                    <option value="{{ $secenek }}" @selected(old('konusma_stili', $ayar->konusma_stili) === $secenek)>{{ $secenek }}
                                    </option>
                                @endforeach
                            </select></div>
                    </div>
                    <div class="mt-6">@include('admin.partials.ai-seviye-kaydiricilari')</div>
                </section>

                <section id="model" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div>
                            <p class="studio-kicker">Model</p>
                            <h3 class="studio-title">Sağlayıcı</h3>
                        </div>
                    </div>
                    <div class="studio-form-grid studio-form-grid--2">
                        <div><label class="studio-label" for="saglayici_tipi">Ana sağlayıcı</label><select
                                id="saglayici_tipi" name="saglayici_tipi" class="studio-select" x-model="provider">
                                <option value="gemini">Google Gemini</option>
                                <option value="openai">OpenAI</option>
                            </select></div>
                        <div>
                            <label class="studio-label" for="model_adi_openai">Ana model</label>
                            <input type="hidden" name="model_adi" value="gemini-2.5-flash"
                                x-bind:disabled="provider !== 'gemini'">
                            <select id="model_adi_openai" name="model_adi" class="studio-select"
                                x-bind:disabled="provider !== 'openai'">
                                @foreach ($anaModelSecenekleri as $model)
                                    <option value="{{ $model }}" @selected(old('model_adi', $ayar->model_adi) === $model)>{{ $model }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="studio-label" for="yedek_saglayici_tipi">Yedek sağlayıcı</label><select
                                id="yedek_saglayici_tipi" name="yedek_saglayici_tipi" class="studio-select"
                                x-model="backupProvider">
                                <option value="">Yok</option>
                                <option value="gemini">Google Gemini</option>
                                <option value="openai">OpenAI</option>
                            </select></div>
                        <div>
                            <label class="studio-label" for="yedek_model_adi_openai">Yedek model</label>
                            <input type="hidden" name="yedek_model_adi" value="gemini-2.5-flash"
                                x-bind:disabled="backupProvider !== 'gemini'">
                            <select id="yedek_model_adi_openai" name="yedek_model_adi" class="studio-select"
                                x-bind:disabled="backupProvider !== 'openai'">
                                <option value="">Seçiniz...</option>
                                @foreach ($yedekModelSecenekleri as $model)
                                    <option value="{{ $model }}" @selected(old('yedek_model_adi', $ayar->yedek_model_adi) === $model)>{{ $model }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="studio-form-grid studio-form-grid--3 mt-6">
                        <div x-data="{ value: {{ old('temperature', $ayar->temperature ?? 0.9) }} }" class="studio-slider">
                            <div class="studio-slider__top">
                                <p class="studio-slider__title">Temperature</p>
                                <div class="studio-slider__value"><span x-text="value"></span></div>
                            </div><input type="range" name="temperature" min="0" max="2" step="0.1"
                                x-model="value" class="studio-range">
                        </div>
                        <div x-data="{ value: {{ old('top_p', $ayar->top_p ?? 0.95) }} }" class="studio-slider">
                            <div class="studio-slider__top">
                                <p class="studio-slider__title">Top P</p>
                                <div class="studio-slider__value"><span x-text="value"></span></div>
                            </div><input type="range" name="top_p" min="0" max="1" step="0.05"
                                x-model="value" class="studio-range">
                        </div>
                        <div><label class="studio-label" for="max_output_tokens">Maksimum çıktı token</label><input
                                id="max_output_tokens" type="number" name="max_output_tokens"
                                value="{{ old('max_output_tokens', $ayar->max_output_tokens ?? 1024) }}" min="64"
                                max="8192" class="studio-input" placeholder="1024"></div>
                    </div>
                </section>

                <section id="mesaj" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div>
                            <p class="studio-kicker">Mesaj</p>
                            <h3 class="studio-title">Mesaj akışı</h3>
                        </div>
                    </div>
                    <div class="studio-copy-block" style="margin-bottom: 1rem;">
                        Cevaplar bu araliga ve rastgele gecikmeye gore planlanir. AI yalnizca cevrim iciyse ve aktif saat penceresindeyse cevap uretir.
                    </div>
                    <div class="studio-form-grid studio-form-grid--4">
                        <div><label class="studio-label" for="minimum_cevap_suresi_saniye">Min. cevap süresi</label><input
                                id="minimum_cevap_suresi_saniye" type="number" name="minimum_cevap_suresi_saniye"
                                value="{{ old('minimum_cevap_suresi_saniye', $ayar->minimum_cevap_suresi_saniye) }}"
                                min="0" class="studio-input" placeholder="30"></div>
                        <div><label class="studio-label" for="maksimum_cevap_suresi_saniye">Maks. cevap
                                süresi</label><input id="maksimum_cevap_suresi_saniye" type="number"
                                name="maksimum_cevap_suresi_saniye"
                                value="{{ old('maksimum_cevap_suresi_saniye', $ayar->maksimum_cevap_suresi_saniye) }}"
                                min="0" class="studio-input" placeholder="180"></div>
                        <div><label class="studio-label" for="mesaj_uzunlugu_min">Min. mesaj uzunluğu</label><input
                                id="mesaj_uzunlugu_min" type="number" name="mesaj_uzunlugu_min"
                                value="{{ old('mesaj_uzunlugu_min', $ayar->mesaj_uzunlugu_min) }}" min="1"
                                class="studio-input" placeholder="12"></div>
                        <div><label class="studio-label" for="mesaj_uzunlugu_max">Maks. mesaj uzunluğu</label><input
                                id="mesaj_uzunlugu_max" type="number" name="mesaj_uzunlugu_max"
                                value="{{ old('mesaj_uzunlugu_max', $ayar->mesaj_uzunlugu_max) }}" min="1"
                                class="studio-input" placeholder="280"></div>
                        <div><label class="studio-label" for="gunluk_konusma_limiti">Günlük konuşma limiti</label><input
                                id="gunluk_konusma_limiti" type="number" name="gunluk_konusma_limiti"
                                value="{{ old('gunluk_konusma_limiti', $ayar->gunluk_konusma_limiti) }}" min="0"
                                class="studio-input" placeholder="60"></div>
                        <div><label class="studio-label" for="tek_kullanici_gunluk_mesaj_limiti">Tek kullanıcı
                                limiti</label><input id="tek_kullanici_gunluk_mesaj_limiti" type="number"
                                name="tek_kullanici_gunluk_mesaj_limiti"
                                value="{{ old('tek_kullanici_gunluk_mesaj_limiti', $ayar->tek_kullanici_gunluk_mesaj_limiti) }}"
                                min="0" class="studio-input" placeholder="12"></div>
                    </div>
                    <div class="studio-toggle-grid studio-toggle-grid--2 mt-6">
                        <label class="studio-toggle"><input type="hidden" name="ilk_mesaj_atar_mi" value="0">
                            <div class="studio-toggle__row">
                                <p class="studio-toggle__title">İlk mesaj atsın</p><input type="checkbox"
                                    name="ilk_mesaj_atar_mi" value="1" @checked(old('ilk_mesaj_atar_mi', $ayar->ilk_mesaj_atar_mi))
                                    class="studio-check">
                            </div>
                        </label>
                    </div>
                    <div class="mt-5"><label class="studio-label" for="ilk_mesaj_sablonu">İlk mesaj şablonu</label>
                        <textarea id="ilk_mesaj_sablonu" name="ilk_mesaj_sablonu" rows="4" class="studio-textarea"
                            placeholder="Merhaba, nasılsın?">{{ old('ilk_mesaj_sablonu', $ayar->ilk_mesaj_sablonu) }}</textarea>
                    </div>
                </section>

                <section id="zamanlama" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div>
                            <p class="studio-kicker">Zamanlama</p>
                            <h3 class="studio-title">Aktif saatler</h3>
                        </div>
                    </div>
                    <div class="studio-copy-block" style="margin-bottom: 1rem;">
                        Uyku penceresinde cevap akis durur. Aktif saat disina tasan mesajlar, bir sonraki uygun pencereye kaydirilir.
                    </div>
                    <div class="studio-form-grid studio-form-grid--3">
                        <div><label class="studio-label" for="saat_dilimi">Saat dilimi</label><input id="saat_dilimi"
                                type="text" name="saat_dilimi"
                                value="{{ old('saat_dilimi', $ayar->saat_dilimi ?? 'Europe/Istanbul') }}"
                                class="studio-input" placeholder="Europe/Istanbul"></div>
                        <div><label class="studio-label" for="uyku_baslangic">Uyku başlangıcı</label><input
                                id="uyku_baslangic" type="time" name="uyku_baslangic"
                                value="{{ old('uyku_baslangic', $ayar->uyku_baslangic ?? '23:00') }}"
                                class="studio-input"></div>
                        <div><label class="studio-label" for="uyku_bitis">Uyku bitişi</label><input id="uyku_bitis"
                                type="time" name="uyku_bitis"
                                value="{{ old('uyku_bitis', $ayar->uyku_bitis ?? '07:30') }}" class="studio-input"></div>
                        <div><label class="studio-label" for="hafta_sonu_uyku_baslangic">Hafta sonu
                                başlangıç</label><input id="hafta_sonu_uyku_baslangic" type="time"
                                name="hafta_sonu_uyku_baslangic"
                                value="{{ old('hafta_sonu_uyku_baslangic', $ayar->hafta_sonu_uyku_baslangic) }}"
                                class="studio-input"></div>
                        <div><label class="studio-label" for="hafta_sonu_uyku_bitis">Hafta sonu bitiş</label><input
                                id="hafta_sonu_uyku_bitis" type="time" name="hafta_sonu_uyku_bitis"
                                value="{{ old('hafta_sonu_uyku_bitis', $ayar->hafta_sonu_uyku_bitis) }}"
                                class="studio-input"></div>
                        <div><label class="studio-label" for="rastgele_gecikme_dakika">Rastgele gecikme</label><input
                                id="rastgele_gecikme_dakika" type="number" name="rastgele_gecikme_dakika"
                                value="{{ old('rastgele_gecikme_dakika', $ayar->rastgele_gecikme_dakika ?? 15) }}"
                                min="0" class="studio-input" placeholder="15"></div>
                    </div>
                </section>

                <section id="kurallar" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div>
                            <p class="studio-kicker">Kurallar</p>
                            <h3 class="studio-title">Kurallar</h3>
                        </div>
                    </div>
                    <div><label class="studio-label" for="sistem_komutu">Sistem komutu</label>
                        <textarea id="sistem_komutu" name="sistem_komutu" rows="6" class="studio-textarea"
                            placeholder="Karakterin asla ihlal etmemesi gereken sistem kuralı">{{ old('sistem_komutu', $ayar->sistem_komutu) }}</textarea>
                    </div>
                    <div class="studio-form-grid studio-form-grid--2 mt-6">
                        <div><label class="studio-label" for="yasakli_konular">Yasaklı konular</label>
                            <textarea id="yasakli_konular" name="yasakli_konular" rows="7" class="studio-textarea"
                                placeholder="Her satıra bir konu yazın">{{ old('yasakli_konular', is_array($ayar->yasakli_konular) ? implode("\n", $ayar->yasakli_konular) : $ayar->yasakli_konular) }}</textarea>
                        </div>
                        <div><label class="studio-label" for="zorunlu_kurallar">Zorunlu kurallar</label>
                            <textarea id="zorunlu_kurallar" name="zorunlu_kurallar" rows="7" class="studio-textarea"
                                placeholder="Her satıra bir kural yazın">{{ old('zorunlu_kurallar', is_array($ayar->zorunlu_kurallar) ? implode("\n", $ayar->zorunlu_kurallar) : $ayar->zorunlu_kurallar) }}</textarea>
                        </div>
                    </div>
                </section>

                <section class="studio-card">
                    <div class="studio-actions">
                        <h3 class="studio-title">Kaydet</h3>
                        <div class="studio-actions__buttons">
                            <a href="{{ route('admin.ai.goster', $kullanici) }}"
                                class="studio-button studio-button--ghost">Vazgeç</a>
                            <button type="submit" class="studio-button studio-button--primary">Değişiklikleri
                                kaydet</button>
                        </div>
                    </div>
                </section>
            </form>

            <aside class="studio-sidebar">
                <section class="studio-card">
                    <p class="studio-kicker">Bölümler</p>
                    <nav class="studio-nav mt-4">
                        @foreach ($sections as $section)
                            <a href="#{{ $section['id'] }}" class="studio-nav__link">
                                <span>{{ $section['label'] }}</span>
                                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                </svg>
                            </a>
                        @endforeach
                    </nav>
                </section>

                <section class="studio-card">
                    <p class="studio-kicker">Özet</p>
                    <div class="studio-meta mt-4">
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Hesap Durumu</p>
                            <p class="studio-meta__value">{{ ucfirst($kullanici->hesap_durumu) }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Ana Model</p>
                            <p class="studio-meta__value">{{ $ayar->model_adi ?? 'Tanımsız' }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Yedek Model</p>
                            <p class="studio-meta__value">{{ $ayar->yedek_model_adi ?: 'Yok' }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Hafıza</p>
                            <p class="studio-meta__value">{{ $ayar->hafiza_aktif_mi ? 'Aktif' : 'Pasif' }}</p>
                        </div>
                    </div>
                </section>

                <section class="studio-card">
                    <p class="studio-kicker">Aksiyonlar</p>
                    <div class="studio-stack mt-4">
                        <a href="{{ route('admin.ai.goster', $kullanici) }}" class="studio-linkcard">
                            <span>Detayı gör</span>
                            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                            </svg>
                        </a>
                        <a href="{{ route('admin.ai.index') }}" class="studio-linkcard">
                            <span>AI listesi</span>
                            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                            </svg>
                        </a>
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
