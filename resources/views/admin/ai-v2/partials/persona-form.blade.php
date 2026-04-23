@php
    $isCreate = ($mode ?? 'edit') === 'create';
    $kullanici = $kullanici ?? null;
    $persona = $persona ?? null;
    $selectedModel = old('model_adi', data_get($persona?->metadata, 'model_adi', array_key_first($modelOptions)));
    $selectedSecondaryLanguages = old('ikinci_diller', $persona?->ikinci_diller ?? []);
    if (!is_array($selectedSecondaryLanguages)) {
        $selectedSecondaryLanguages = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $selectedSecondaryLanguages) ?: [])));
    }
    $selectedCountry = old('persona_ulke', $persona?->persona_ulke ?? old('ulke', $kullanici?->ulke ?? 'Turkiye'));
    $selectedRegion = old('persona_bolge', $persona?->persona_bolge);
    $selectedCity = old('persona_sehir', $persona?->persona_sehir);
    $behaviorValues = collect($behaviorSliders)->mapWithKeys(function (array $meta, string $field) use ($persona) {
        return [$field => (int) old($field, $persona?->{$field} ?? ($meta['default'] ?? 5))];
    })->all();
@endphp

<form method="POST" action="{{ $action }}" class="studio studio--ai space-y-6"
    x-data="{
        locationCatalog: @js($locationCatalog),
        personaCountry: @js($selectedCountry),
        personaRegion: @js($selectedRegion),
        personaCity: @js($selectedCity),
        behavior: @js($behaviorValues),
        regions() {
            return Object.keys(this.locationCatalog[this.personaCountry]?.regions ?? {});
        },
        cities() {
            return this.locationCatalog[this.personaCountry]?.regions?.[this.personaRegion] ?? [];
        },
        syncLocation() {
            if (!this.regions().includes(this.personaRegion)) {
                this.personaRegion = '';
                this.personaCity = '';
            }

            if (!this.cities().includes(this.personaCity)) {
                this.personaCity = '';
            }
        }
    }"
    x-init="syncLocation()">
    @csrf
    @unless ($isCreate)
        @method('PUT')
    @endunless

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ $backUrl }}" class="studio-button studio-button--ghost">{{ $backLabel }}</a>
            <h1 class="text-2xl font-semibold text-slate-950">{{ $title }}</h1>
        </div>
        @if (!$isCreate && $kullanici)
            <div class="text-sm font-medium text-slate-500">{{ '@' . $kullanici->kullanici_adi }}</div>
        @endif
    </div>

    @if ($errors->any())
        <div class="studio-notice studio-notice--danger">
            {{ $errors->first() }}
        </div>
    @endif

    @include('admin.ai-v2.partials.navigation')

    <div class="grid gap-6 xl:grid-cols-[1.08fr,1fr]">
        <section class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Kimlik ve Hesap</h2>
                </div>
            </div>

            <div class="studio-form-grid studio-form-grid--4">
                <label>
                    <span class="studio-label">Ad</span>
                    <input class="studio-input" type="text" name="ad" value="{{ old('ad', $kullanici?->ad) }}" required>
                </label>
                <label>
                    <span class="studio-label">Soyad</span>
                    <input class="studio-input" type="text" name="soyad" value="{{ old('soyad', $kullanici?->soyad) }}">
                </label>
                <label>
                    <span class="studio-label">Kullanici Adi</span>
                    <input class="studio-input" type="text" name="kullanici_adi" value="{{ old('kullanici_adi', $kullanici?->kullanici_adi) }}" required>
                </label>
                <label>
                    <span class="studio-label">Hesap Durumu</span>
                    <select class="studio-select" name="hesap_durumu">
                        @foreach ($dropdowns['account_statuses'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('hesap_durumu', $kullanici?->hesap_durumu ?? 'aktif') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="studio-label">Cinsiyet</span>
                    <select class="studio-select" name="cinsiyet">
                        @foreach ($dropdowns['genders'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('cinsiyet', $kullanici?->cinsiyet ?? 'kadin') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="studio-label">Dogum Yili</span>
                    <input class="studio-input" type="number" name="dogum_yili" min="1950" max="{{ now()->year }}" value="{{ old('dogum_yili', $kullanici?->dogum_yili) }}">
                </label>
                <label>
                    <span class="studio-label">Ulke</span>
                    <select class="studio-select" name="ulke">
                        <option value="">Seciniz</option>
                        @foreach ($countryOptions as $country)
                            <option value="{{ $country }}" @selected(old('ulke', $kullanici?->ulke ?? 'Turkiye') === $country)>{{ $country }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="studio-label">Model</span>
                    <select class="studio-select" name="model_adi">
                        @foreach ($modelOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedModel === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="md:col-span-4">
                    <span class="studio-label">Biyografi</span>
                    <textarea class="studio-textarea" name="biyografi" rows="4">{{ old('biyografi', $kullanici?->biyografi) }}</textarea>
                </label>
            </div>
        </section>

        <section class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Calisma Modu</h2>
                </div>
            </div>

            <div class="studio-toggle-grid studio-form-grid--2">
                <label class="studio-toggle">
                    <div class="studio-toggle__row">
                        <div><div class="studio-toggle__title">Persona Aktif</div></div>
                        <input class="studio-check" type="checkbox" name="aktif_mi" value="1" @checked(old('aktif_mi', $persona?->aktif_mi ?? true))>
                    </div>
                </label>
                <label class="studio-toggle">
                    <div class="studio-toggle__row">
                        <div><div class="studio-toggle__title">Dating Aktif</div></div>
                        <input class="studio-check" type="checkbox" name="dating_aktif_mi" value="1" @checked(old('dating_aktif_mi', $persona?->dating_aktif_mi ?? true))>
                    </div>
                </label>
                <label class="studio-toggle">
                    <div class="studio-toggle__row">
                        <div><div class="studio-toggle__title">Instagram Aktif</div></div>
                        <input class="studio-check" type="checkbox" name="instagram_aktif_mi" value="1" @checked(old('instagram_aktif_mi', $persona?->instagram_aktif_mi ?? true))>
                    </div>
                </label>
                <label class="studio-toggle">
                    <div class="studio-toggle__row">
                        <div><div class="studio-toggle__title">Ilk Mesaj Atar</div></div>
                        <input class="studio-check" type="checkbox" name="ilk_mesaj_atar_mi" value="1" @checked(old('ilk_mesaj_atar_mi', $persona?->ilk_mesaj_atar_mi ?? true))>
                    </div>
                </label>
            </div>

            <div class="ai-studio-form-grid mt-6">
                <label>
                    <span class="studio-label">Persona Ozeti</span>
                    <textarea class="studio-textarea" name="persona_ozeti" rows="6">{{ old('persona_ozeti', $persona?->persona_ozeti) }}</textarea>
                </label>
                <label>
                    <span class="studio-label">Ilk Mesaj Tonu</span>
                    <textarea class="studio-textarea" name="ilk_mesaj_tonu" rows="4">{{ old('ilk_mesaj_tonu', $persona?->ilk_mesaj_tonu) }}</textarea>
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
                <span class="studio-label">Ana Dil</span>
                <select class="studio-select" name="ana_dil_kodu">
                    @foreach ($dropdowns['languages'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('ana_dil_kodu', $persona?->ana_dil_kodu ?? 'tr') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="md:col-span-3">
                <span class="studio-label">Ikinci Diller</span>
                <select class="studio-select min-h-[10rem]" name="ikinci_diller[]" multiple>
                    @foreach ($dropdowns['languages'] as $code => $label)
                        <option value="{{ $label }}" @selected(in_array($label, $selectedSecondaryLanguages, true))>{{ $label }}</option>
                    @endforeach
                    @foreach (array_diff($selectedSecondaryLanguages, array_values($dropdowns['languages'])) as $label)
                        <option value="{{ $label }}" selected>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Persona Ulke</span>
                <select class="studio-select" name="persona_ulke" x-model="personaCountry" @change="syncLocation()">
                    <option value="">Seciniz</option>
                    @foreach ($countryOptions as $country)
                        <option value="{{ $country }}" @selected($selectedCountry === $country)>{{ $country }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Persona Bolge</span>
                <select class="studio-select" name="persona_bolge" x-model="personaRegion" @change="syncLocation()">
                    <option value="">Seciniz</option>
                    <template x-for="region in regions()" :key="region">
                        <option :value="region" x-text="region"></option>
                    </template>
                    @if ($selectedRegion)
                        <option value="{{ $selectedRegion }}" x-show="false">{{ $selectedRegion }}</option>
                    @endif
                </select>
            </label>
            <label>
                <span class="studio-label">Persona Sehir</span>
                <select class="studio-select" name="persona_sehir" x-model="personaCity">
                    <option value="">Seciniz</option>
                    <template x-for="city in cities()" :key="city">
                        <option :value="city" x-text="city"></option>
                    </template>
                    @if ($selectedCity)
                        <option value="{{ $selectedCity }}" x-show="false">{{ $selectedCity }}</option>
                    @endif
                </select>
            </label>
            <label>
                <span class="studio-label">Yasam Cevresi</span>
                <input class="studio-input" type="text" name="persona_mahalle" value="{{ old('persona_mahalle', $persona?->persona_mahalle) }}">
            </label>
            <label>
                <span class="studio-label">Kulturel Koken</span>
                <input class="studio-input" type="text" name="kulturel_koken" value="{{ old('kulturel_koken', $persona?->kulturel_koken) }}">
            </label>
            <label>
                <span class="studio-label">Uyruk</span>
                <select class="studio-select" name="uyruk">
                    <option value="">Seciniz</option>
                    @foreach ($countryOptions as $country)
                        <option value="{{ $country }}" @selected(old('uyruk', $persona?->uyruk) === $country)>{{ $country }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Yasam Tarzi</span>
                <select class="studio-select" name="yasam_tarzi">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['lifestyles'] as $option)
                        <option value="{{ $option }}" @selected(old('yasam_tarzi', $persona?->yasam_tarzi) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Yas Araligi</span>
                <select class="studio-select" name="yas_araligi">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['age_ranges'] as $option)
                        <option value="{{ $option }}" @selected(old('yas_araligi', $persona?->yas_araligi) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
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
                <select class="studio-select" name="meslek">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['professions'] as $option)
                        <option value="{{ $option }}" @selected(old('meslek', $persona?->meslek) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Sektor</span>
                <select class="studio-select" name="sektor">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['sectors'] as $option)
                        <option value="{{ $option }}" @selected(old('sektor', $persona?->sektor) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Egitim</span>
                <select class="studio-select" name="egitim">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['education_levels'] as $option)
                        <option value="{{ $option }}" @selected(old('egitim', $persona?->egitim) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Okul / Bolum</span>
                <input class="studio-input" type="text" name="okul_bolum" value="{{ old('okul_bolum', $persona?->okul_bolum) }}">
            </label>
            <label>
                <span class="studio-label">Gunluk Rutin</span>
                <textarea class="studio-textarea" name="gunluk_rutin" rows="4">{{ old('gunluk_rutin', $persona?->gunluk_rutin) }}</textarea>
            </label>
            <label>
                <span class="studio-label">Hobiler</span>
                <textarea class="studio-textarea" name="hobiler" rows="4">{{ old('hobiler', $persona?->hobiler) }}</textarea>
            </label>
            <label>
                <span class="studio-label">Sevdigi Mekanlar</span>
                <textarea class="studio-textarea" name="sevdigi_mekanlar" rows="4">{{ old('sevdigi_mekanlar', $persona?->sevdigi_mekanlar) }}</textarea>
            </label>
            <label>
                <span class="studio-label">Aile / Arkadas Notu</span>
                <textarea class="studio-textarea" name="aile_arkadas_notu" rows="4">{{ old('aile_arkadas_notu', $persona?->aile_arkadas_notu) }}</textarea>
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
                <span class="studio-label">Konusma Tonu</span>
                <select class="studio-select" name="konusma_tonu">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['conversation_tones'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('konusma_tonu', $persona?->konusma_tonu) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Konusma Stili</span>
                <select class="studio-select" name="konusma_stili">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['conversation_styles'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('konusma_stili', $persona?->konusma_stili) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Iliski Gecmisi Tonu</span>
                <select class="studio-select" name="iliski_gecmisi_tonu">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['relationship_history_tones'] as $option)
                        <option value="{{ $option }}" @selected(old('iliski_gecmisi_tonu', $persona?->iliski_gecmisi_tonu) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="studio-label">Cevap Ritmi</span>
                <select class="studio-select" name="cevap_ritmi">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['response_rhythms'] as $option)
                        <option value="{{ $option }}" @selected(old('cevap_ritmi', $persona?->cevap_ritmi) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label class="md:col-span-2">
                <span class="studio-label">Konusma Imzasi</span>
                <textarea class="studio-textarea" name="konusma_imzasi" rows="4">{{ old('konusma_imzasi', $persona?->konusma_imzasi) }}</textarea>
            </label>
            <label>
                <span class="studio-label">Emoji Aliskanligi</span>
                <select class="studio-select" name="emoji_aliskanligi">
                    <option value="">Seciniz</option>
                    @foreach ($dropdowns['emoji_habits'] as $option)
                        <option value="{{ $option }}" @selected(old('emoji_aliskanligi', $persona?->emoji_aliskanligi) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label class="md:col-span-2">
                <span class="studio-label">Kacinilacak Detaylar</span>
                <textarea class="studio-textarea" name="kacinilacak_persona_detaylari" rows="4">{{ old('kacinilacak_persona_detaylari', $persona?->kacinilacak_persona_detaylari) }}</textarea>
            </label>
        </div>
    </section>

    <section class="studio-card">
        <div class="studio-card__header">
            <div>
                <h2 class="studio-title">Davranis Sliderlari</h2>
            </div>
        </div>

        <div class="space-y-6">
            @foreach ($behaviorSliderGroups as $group => $sliders)
                <div class="space-y-4">
                    <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $group }}</div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($sliders as $field => $meta)
                            <div class="studio-slider">
                                <div class="studio-slider__top">
                                    <div>
                                        <div class="studio-slider__title">{{ $meta['label'] }}</div>
                                    </div>
                                    <div class="studio-slider__value" x-text="behavior['{{ $field }}']"></div>
                                </div>
                                <input class="studio-range" type="range" min="0" max="10" name="{{ $field }}" x-model="behavior['{{ $field }}']">
                                <div class="studio-range__legend">
                                    <span>{{ $meta['legend'][0] ?? 'Dusuk' }}</span>
                                    <span>{{ $meta['legend'][1] ?? 'Yuksek' }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
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
                    <input class="studio-input" type="number" name="mesaj_uzunlugu_min" min="8" max="400" value="{{ old('mesaj_uzunlugu_min', $persona?->mesaj_uzunlugu_min ?? 18) }}">
                </label>
                <label>
                    <span class="studio-label">Mesaj Max</span>
                    <input class="studio-input" type="number" name="mesaj_uzunlugu_max" min="20" max="800" value="{{ old('mesaj_uzunlugu_max', $persona?->mesaj_uzunlugu_max ?? 220) }}">
                </label>
                <label>
                    <span class="studio-label">Cevap Min Saniye</span>
                    <input class="studio-input" type="number" name="minimum_cevap_suresi_saniye" min="0" max="600" value="{{ old('minimum_cevap_suresi_saniye', $persona?->minimum_cevap_suresi_saniye ?? 4) }}">
                </label>
                <label>
                    <span class="studio-label">Cevap Max Saniye</span>
                    <input class="studio-input" type="number" name="maksimum_cevap_suresi_saniye" min="0" max="1200" value="{{ old('maksimum_cevap_suresi_saniye', $persona?->maksimum_cevap_suresi_saniye ?? 24) }}">
                </label>
            </div>

            <div class="studio-form-grid studio-form-grid--2 mt-6">
                <label class="md:col-span-2">
                    <span class="studio-label">Saat Dilimi</span>
                    <input class="studio-input" type="text" name="saat_dilimi" value="{{ old('saat_dilimi', $persona?->saat_dilimi ?? config('app.timezone')) }}" placeholder="Europe/Istanbul">
                </label>
                <label>
                    <span class="studio-label">Uyku Baslangic</span>
                    <input class="studio-input" type="time" name="uyku_baslangic" value="{{ old('uyku_baslangic', $persona?->uyku_baslangic ?? '01:00') }}">
                </label>
                <label>
                    <span class="studio-label">Uyku Bitis</span>
                    <input class="studio-input" type="time" name="uyku_bitis" value="{{ old('uyku_bitis', $persona?->uyku_bitis ?? '08:00') }}">
                </label>
                <label>
                    <span class="studio-label">Hafta Sonu Baslangic</span>
                    <input class="studio-input" type="time" name="hafta_sonu_uyku_baslangic" value="{{ old('hafta_sonu_uyku_baslangic', $persona?->hafta_sonu_uyku_baslangic ?? '02:00') }}">
                </label>
                <label>
                    <span class="studio-label">Hafta Sonu Bitis</span>
                    <input class="studio-input" type="time" name="hafta_sonu_uyku_bitis" value="{{ old('hafta_sonu_uyku_bitis', $persona?->hafta_sonu_uyku_bitis ?? '10:00') }}">
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
                    <textarea class="studio-textarea" name="blocked_topics" rows="8">{{ old('blocked_topics', $blockedTopicsText) }}</textarea>
                </label>
                <label>
                    <span class="studio-label">Persona Zorunlu Kurallar</span>
                    <textarea class="studio-textarea" name="required_rules" rows="8">{{ old('required_rules', $requiredRulesText) }}</textarea>
                </label>
            </div>
        </section>
    </div>

    <div class="ai-studio-sticky-actions">
        <div class="studio-card">
            <div class="studio-actions">
                <div class="studio-actions__buttons">
                    <a href="{{ $backUrl }}" class="studio-button studio-button--ghost">{{ $cancelLabel }}</a>
                    <button class="studio-button studio-button--primary">{{ $submitLabel }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
