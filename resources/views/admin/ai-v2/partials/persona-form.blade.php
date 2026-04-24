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

<form method="POST" action="{{ $action }}" class="ai-console space-y-6"
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

    <section class="ai-console-hero">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.18fr)_22rem] xl:items-start">
            <div>
                <div class="ai-console-kicker">{{ $isCreate ? 'Persona create mode' : 'Persona editor mode' }}</div>
                <h1 class="ai-console-title">{{ $title }}</h1>

                <div class="ai-console-chip-row">
                    <span class="ai-console-chip">{{ $modelOptions[$selectedModel] ?? $selectedModel }}</span>
                    <span class="ai-console-chip">{{ old('persona_ulke', $persona?->persona_ulke ?? old('ulke', $kullanici?->ulke ?? 'Turkiye')) ?: 'Ulke secimi bekleniyor' }}</span>
                    @if (!$isCreate && $kullanici)
                        <span class="ai-console-chip">{{ '@' . $kullanici->kullanici_adi }}</span>
                    @endif
                </div>

                <div class="ai-console-actions">
                    <a href="{{ $backUrl }}" class="ai-console-button ai-console-button--ghost">{{ $backLabel }}</a>
                    <button class="ai-console-button ai-console-button--primary">{{ $submitLabel }}</button>
                </div>
            </div>

            <div class="ai-console-panel">
                <div class="ai-console-kicker">Editor Snapshot</div>
                <div class="ai-console-title !mt-2 !text-[2.2rem]">{{ $isCreate ? 'Draft' : 'Live' }}</div>
                <div class="ai-console-kpi-grid mt-5">
                    <div class="ai-console-kpi">
                        <div class="ai-console-kpi__label">Ana Dil</div>
                        <div class="ai-console-kpi__value">{{ $dropdowns['languages'][old('ana_dil_kodu', $persona?->ana_dil_kodu ?? 'tr')] ?? 'Turkce' }}</div>
                    </div>
                    <div class="ai-console-kpi">
                        <div class="ai-console-kpi__label">Hesap Durumu</div>
                        <div class="ai-console-kpi__value">{{ $dropdowns['account_statuses'][old('hesap_durumu', $kullanici?->hesap_durumu ?? 'aktif')] ?? 'Aktif' }}</div>
                    </div>
                    <div class="ai-console-kpi md:col-span-2">
                        <div class="ai-console-kpi__label">Ulke</div>
                        <div class="ai-console-kpi__value">{{ old('ulke', $kullanici?->ulke ?? 'Turkiye') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm font-medium text-rose-300">
            {{ $errors->first() }}
        </div>
    @endif

    @include('admin.ai-v2.partials.navigation')

    <div class="ai-console-grid">
        <section class="ai-console-panel">
            <div class="ai-console-panel__header">
                <div>
                    <h2 class="ai-console-panel__title">Kimlik ve Hesap</h2>
                    <p class="ai-console-panel__copy">Temel profil kimligi ve model secimi.</p>
                </div>
            </div>

            <div class="ai-console-control-group ai-console-control-group--4">
                <label>
                    <span class="ai-console-label">Ad</span>
                    <input class="ai-console-input" type="text" name="ad" value="{{ old('ad', $kullanici?->ad) }}" required placeholder="Orn. Lina">
                </label>
                <label>
                    <span class="ai-console-label">Soyad</span>
                    <input class="ai-console-input" type="text" name="soyad" value="{{ old('soyad', $kullanici?->soyad) }}" placeholder="Orn. Stone">
                </label>
                <label>
                    <span class="ai-console-label">Kullanici Adi</span>
                    <input class="ai-console-input" type="text" name="kullanici_adi" value="{{ old('kullanici_adi', $kullanici?->kullanici_adi) }}" required placeholder="orn. lina_studio_ai">
                </label>
                <label>
                    <span class="ai-console-label">Hesap Durumu</span>
                    <select class="ai-console-select" name="hesap_durumu">
                        @foreach ($dropdowns['account_statuses'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('hesap_durumu', $kullanici?->hesap_durumu ?? 'aktif') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Cinsiyet</span>
                    <select class="ai-console-select" name="cinsiyet">
                        @foreach ($dropdowns['genders'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('cinsiyet', $kullanici?->cinsiyet ?? 'kadin') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Dogum Yili</span>
                    <input class="ai-console-input" type="number" name="dogum_yili" min="1950" max="{{ now()->year }}" value="{{ old('dogum_yili', $kullanici?->dogum_yili) }}" placeholder="1998">
                </label>
                <label>
                    <span class="ai-console-label">Ulke</span>
                    <select class="ai-console-select" name="ulke">
                        <option value="">Seciniz</option>
                        @foreach ($countryOptions as $country)
                            <option value="{{ $country }}" @selected(old('ulke', $kullanici?->ulke ?? 'Turkiye') === $country)>{{ $country }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Model</span>
                    <select class="ai-console-select" name="model_adi">
                        @foreach ($modelOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedModel === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="md:col-span-4">
                    <span class="ai-console-label">Biyografi</span>
                    <textarea class="ai-console-textarea" name="biyografi" rows="4" placeholder="Profilde gorunen kisa tanitim metni.">{{ old('biyografi', $kullanici?->biyografi) }}</textarea>
                </label>
            </div>
        </section>

        <section class="ai-console-panel">
            <div class="ai-console-panel__header">
                <div>
                    <h2 class="ai-console-panel__title">Calisma Modu</h2>
                    <p class="ai-console-panel__copy">Kanallar, ilk mesaj davranisi ve genel aktiflik.</p>
                </div>
            </div>

            <div class="ai-console-card-grid ai-console-card-grid--2">
                <label class="ai-console-toggle flex items-start justify-between gap-4">
                    <div>
                        <div class="ai-console-label !text-xs">Persona Aktif</div>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Bu karakterin cevap uretimini acar.</p>
                    </div>
                    <input class="mt-1 h-5 w-5 accent-pink-500" type="checkbox" name="aktif_mi" value="1" @checked(old('aktif_mi', $persona?->aktif_mi ?? true))>
                </label>
                <label class="ai-console-toggle flex items-start justify-between gap-4">
                    <div>
                        <div class="ai-console-label !text-xs">Dating Aktif</div>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Dating kanalinda cevap uretir.</p>
                    </div>
                    <input class="mt-1 h-5 w-5 accent-pink-500" type="checkbox" name="dating_aktif_mi" value="1" @checked(old('dating_aktif_mi', $persona?->dating_aktif_mi ?? true))>
                </label>
                <label class="ai-console-toggle flex items-start justify-between gap-4">
                    <div>
                        <div class="ai-console-label !text-xs">Instagram Aktif</div>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Instagram adaptoru bu personayi kullanir.</p>
                    </div>
                    <input class="mt-1 h-5 w-5 accent-pink-500" type="checkbox" name="instagram_aktif_mi" value="1" @checked(old('instagram_aktif_mi', $persona?->instagram_aktif_mi ?? true))>
                </label>
                <label class="ai-console-toggle flex items-start justify-between gap-4">
                    <div>
                        <div class="ai-console-label !text-xs">Ilk Mesaj Atar</div>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Match acilisinda kendisi baslatir.</p>
                    </div>
                    <input class="mt-1 h-5 w-5 accent-pink-500" type="checkbox" name="ilk_mesaj_atar_mi" value="1" @checked(old('ilk_mesaj_atar_mi', $persona?->ilk_mesaj_atar_mi ?? true))>
                </label>
            </div>

            <div class="ai-console-control-group ai-console-control-group--2 mt-5">
                <label>
                    <span class="ai-console-label">Persona Ozeti</span>
                    <textarea class="ai-console-textarea" name="persona_ozeti" rows="6" placeholder="Karakterin omurgasini, enerjisini, sosyal tavrini ve birinin aklinda nasil kaldigini yaz.">{{ old('persona_ozeti', $persona?->persona_ozeti) }}</textarea>
                </label>
                <label>
                    <span class="ai-console-label">Ilk Mesaj Tonu</span>
                    <textarea class="ai-console-textarea" name="ilk_mesaj_tonu" rows="6" placeholder="Ilk acilista nasil bir enerjiyle girdigini, ne kadar flortlu ya da mesafeli oldugunu tarif et.">{{ old('ilk_mesaj_tonu', $persona?->ilk_mesaj_tonu) }}</textarea>
                </label>
            </div>
        </section>

        <section class="ai-console-panel">
            <div class="ai-console-panel__header">
                <div>
                    <h2 class="ai-console-panel__title">Kimlik ve Dil</h2>
                </div>
            </div>

            <div class="ai-console-control-group ai-console-control-group--4">
                <label>
                    <span class="ai-console-label">Ana Dil</span>
                    <select class="ai-console-select" name="ana_dil_kodu">
                        @foreach ($dropdowns['languages'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('ana_dil_kodu', $persona?->ana_dil_kodu ?? 'tr') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="md:col-span-3">
                    <span class="ai-console-label">Ikinci Diller</span>
                    <select class="ai-console-select min-h-[10rem]" name="ikinci_diller[]" multiple>
                        @foreach ($dropdowns['languages'] as $code => $label)
                            <option value="{{ $label }}" @selected(in_array($label, $selectedSecondaryLanguages, true))>{{ $label }}</option>
                        @endforeach
                        @foreach (array_diff($selectedSecondaryLanguages, array_values($dropdowns['languages'])) as $label)
                            <option value="{{ $label }}" selected>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Persona Ulke</span>
                    <select class="ai-console-select" name="persona_ulke" x-model="personaCountry" @change="syncLocation()">
                        <option value="">Seciniz</option>
                        @foreach ($countryOptions as $country)
                            <option value="{{ $country }}" @selected($selectedCountry === $country)>{{ $country }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Persona Bolge</span>
                    <select class="ai-console-select" name="persona_bolge" x-model="personaRegion" @change="syncLocation()">
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
                    <span class="ai-console-label">Persona Sehir</span>
                    <select class="ai-console-select" name="persona_sehir" x-model="personaCity">
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
                    <span class="ai-console-label">Yasam Cevresi</span>
                    <select class="ai-console-select" name="persona_mahalle">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['living_environments'] as $option)
                            <option value="{{ $option }}" @selected(old('persona_mahalle', $persona?->persona_mahalle) === $option)>{{ $option }}</option>
                        @endforeach
                        @foreach (collect([old('persona_mahalle', $persona?->persona_mahalle)])->filter()->diff($dropdowns['living_environments']) as $option)
                            <option value="{{ $option }}" selected>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Kulturel Koken</span>
                    <select class="ai-console-select" name="kulturel_koken">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['cultural_origins'] as $option)
                            <option value="{{ $option }}" @selected(old('kulturel_koken', $persona?->kulturel_koken) === $option)>{{ $option }}</option>
                        @endforeach
                        @foreach (collect([old('kulturel_koken', $persona?->kulturel_koken)])->filter()->diff($dropdowns['cultural_origins']) as $option)
                            <option value="{{ $option }}" selected>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Uyruk</span>
                    <select class="ai-console-select" name="uyruk">
                        <option value="">Seciniz</option>
                        @foreach ($countryOptions as $country)
                            <option value="{{ $country }}" @selected(old('uyruk', $persona?->uyruk) === $country)>{{ $country }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Yasam Tarzi</span>
                    <select class="ai-console-select" name="yasam_tarzi">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['lifestyles'] as $option)
                            <option value="{{ $option }}" @selected(old('yasam_tarzi', $persona?->yasam_tarzi) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Yas Araligi</span>
                    <select class="ai-console-select" name="yas_araligi">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['age_ranges'] as $option)
                            <option value="{{ $option }}" @selected(old('yas_araligi', $persona?->yas_araligi) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        <section class="ai-console-panel">
            <div class="ai-console-panel__header">
                <div>
                    <h2 class="ai-console-panel__title">Yasam Detaylari</h2>
                </div>
            </div>

            <div class="ai-console-control-group ai-console-control-group--2">
                <label>
                    <span class="ai-console-label">Meslek</span>
                    <select class="ai-console-select" name="meslek">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['professions'] as $option)
                            <option value="{{ $option }}" @selected(old('meslek', $persona?->meslek) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Sektor</span>
                    <select class="ai-console-select" name="sektor">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['sectors'] as $option)
                            <option value="{{ $option }}" @selected(old('sektor', $persona?->sektor) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Egitim</span>
                    <select class="ai-console-select" name="egitim">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['education_levels'] as $option)
                            <option value="{{ $option }}" @selected(old('egitim', $persona?->egitim) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Okul / Bolum</span>
                    <input class="ai-console-input" type="text" name="okul_bolum" value="{{ old('okul_bolum', $persona?->okul_bolum) }}" placeholder="Orn. Gorsel Iletisim Tasarimi">
                </label>
                <label>
                    <span class="ai-console-label">Gunluk Rutin</span>
                    <textarea class="ai-console-textarea" name="gunluk_rutin" rows="4" placeholder="Sabah, gun ici ve aksam akisini birkac cumleyle tarif et.">{{ old('gunluk_rutin', $persona?->gunluk_rutin) }}</textarea>
                </label>
                <label>
                    <span class="ai-console-label">Hobiler</span>
                    <textarea class="ai-console-textarea" name="hobiler" rows="4" placeholder="Hoslandigi aktiviteleri virgulle ya da kisa cumlelerle yaz.">{{ old('hobiler', $persona?->hobiler) }}</textarea>
                </label>
                <label>
                    <span class="ai-console-label">Sevdigi Mekanlar</span>
                    <textarea class="ai-console-textarea" name="sevdigi_mekanlar" rows="4" placeholder="Kafeler, semtler, sahil, muzeler gibi sevdigi yerleri yaz.">{{ old('sevdigi_mekanlar', $persona?->sevdigi_mekanlar) }}</textarea>
                </label>
                <label>
                    <span class="ai-console-label">Aile / Arkadas Notu</span>
                    <textarea class="ai-console-textarea" name="aile_arkadas_notu" rows="4" placeholder="Ailesiyle ve yakin cevresiyle iliskisini kisaca anlat.">{{ old('aile_arkadas_notu', $persona?->aile_arkadas_notu) }}</textarea>
                </label>
            </div>
        </section>

        <section class="ai-console-panel">
            <div class="ai-console-panel__header">
                <div>
                    <h2 class="ai-console-panel__title">Konusma Gercekciligi</h2>
                </div>
            </div>

            <div class="ai-console-control-group ai-console-control-group--2">
                <label>
                    <span class="ai-console-label">Konusma Tonu</span>
                    <select class="ai-console-select" name="konusma_tonu">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['conversation_tones'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('konusma_tonu', $persona?->konusma_tonu) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Konusma Stili</span>
                    <select class="ai-console-select" name="konusma_stili">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['conversation_styles'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('konusma_stili', $persona?->konusma_stili) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Iliski Gecmisi Tonu</span>
                    <select class="ai-console-select" name="iliski_gecmisi_tonu">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['relationship_history_tones'] as $option)
                            <option value="{{ $option }}" @selected(old('iliski_gecmisi_tonu', $persona?->iliski_gecmisi_tonu) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Cevap Ritmi</span>
                    <select class="ai-console-select" name="cevap_ritmi">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['response_rhythms'] as $option)
                            <option value="{{ $option }}" @selected(old('cevap_ritmi', $persona?->cevap_ritmi) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="md:col-span-2">
                    <span class="ai-console-label">Konusma Imzasi</span>
                    <textarea class="ai-console-textarea" name="konusma_imzasi" rows="4" placeholder="Cok kullandigi bir ifade, cumle ritmi veya kendine ozgu sohbet izi.">{{ old('konusma_imzasi', $persona?->konusma_imzasi) }}</textarea>
                </label>
                <label>
                    <span class="ai-console-label">Emoji Aliskanligi</span>
                    <select class="ai-console-select" name="emoji_aliskanligi">
                        <option value="">Seciniz</option>
                        @foreach ($dropdowns['emoji_habits'] as $option)
                            <option value="{{ $option }}" @selected(old('emoji_aliskanligi', $persona?->emoji_aliskanligi) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="md:col-span-2">
                    <span class="ai-console-label">Kacinilacak Detaylar</span>
                    <textarea class="ai-console-textarea" name="kacinilacak_persona_detaylari" rows="4" placeholder="Bu persona hangi izlenimleri vermemeli, nelerden uzak durmali?">{{ old('kacinilacak_persona_detaylari', $persona?->kacinilacak_persona_detaylari) }}</textarea>
                </label>
            </div>
        </section>

        <section class="ai-console-panel">
            <div class="ai-console-panel__header">
                <div>
                    <h2 class="ai-console-panel__title">Davranis Sliderlari</h2>
                    <p class="ai-console-panel__copy">Buyuk davranis matrisi koyu studio slider kartlari icinde gosterilir.</p>
                </div>
            </div>

            <div class="space-y-6">
                @foreach ($behaviorSliderGroups as $group => $sliders)
                    <div class="space-y-4">
                        <div class="ai-console-kicker">{{ $group }}</div>
                        <div class="ai-console-card-grid ai-console-card-grid--3">
                            @foreach ($sliders as $field => $meta)
                                <div class="ai-console-card">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="ai-console-card__title">{{ $meta['label'] }}</div>
                                        </div>
                                        <div class="text-sm font-bold text-white" x-text="behavior['{{ $field }}'] + '/10'"></div>
                                    </div>
                                    <input class="mt-4 h-2 w-full cursor-pointer accent-pink-500" type="range" min="0" max="10" name="{{ $field }}" x-model="behavior['{{ $field }}']">
                                    <div class="mt-3 flex justify-between text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
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

        <div class="ai-console-grid ai-console-grid--split">
            <section class="ai-console-panel">
                <div class="ai-console-panel__header">
                    <div>
                        <h2 class="ai-console-panel__title">Mesaj Boyu ve Zamanlama</h2>
                        <p class="ai-console-panel__copy">Mesaj uzunlugu, bekleme suresi ve uyku bloklari.</p>
                    </div>
                </div>

                <div class="ai-console-control-group ai-console-control-group--2">
                    <label>
                        <span class="ai-console-label">Mesaj Min</span>
                        <input class="ai-console-input" type="number" name="mesaj_uzunlugu_min" min="8" max="400" value="{{ old('mesaj_uzunlugu_min', $persona?->mesaj_uzunlugu_min ?? 18) }}" placeholder="18">
                    </label>
                    <label>
                        <span class="ai-console-label">Mesaj Max</span>
                        <input class="ai-console-input" type="number" name="mesaj_uzunlugu_max" min="20" max="800" value="{{ old('mesaj_uzunlugu_max', $persona?->mesaj_uzunlugu_max ?? 220) }}" placeholder="220">
                    </label>
                    <label>
                        <span class="ai-console-label">Cevap Min Saniye</span>
                        <input class="ai-console-input" type="number" name="minimum_cevap_suresi_saniye" min="0" max="600" value="{{ old('minimum_cevap_suresi_saniye', $persona?->minimum_cevap_suresi_saniye ?? 4) }}" placeholder="4">
                    </label>
                    <label>
                        <span class="ai-console-label">Cevap Max Saniye</span>
                        <input class="ai-console-input" type="number" name="maksimum_cevap_suresi_saniye" min="0" max="1200" value="{{ old('maksimum_cevap_suresi_saniye', $persona?->maksimum_cevap_suresi_saniye ?? 24) }}" placeholder="24">
                    </label>
                </div>

                <div class="ai-console-control-group ai-console-control-group--2 mt-5">
                    <label class="md:col-span-2">
                        <span class="ai-console-label">Saat Dilimi</span>
                        <input class="ai-console-input" type="text" name="saat_dilimi" value="{{ old('saat_dilimi', $persona?->saat_dilimi ?? config('app.timezone')) }}" placeholder="Europe/Istanbul">
                    </label>
                    <label>
                        <span class="ai-console-label">Uyku Baslangic</span>
                        <input class="ai-console-input" type="time" name="uyku_baslangic" value="{{ old('uyku_baslangic', $persona?->uyku_baslangic ?? '01:00') }}" placeholder="01:00">
                    </label>
                    <label>
                        <span class="ai-console-label">Uyku Bitis</span>
                        <input class="ai-console-input" type="time" name="uyku_bitis" value="{{ old('uyku_bitis', $persona?->uyku_bitis ?? '08:00') }}" placeholder="08:00">
                    </label>
                    <label>
                        <span class="ai-console-label">Hafta Sonu Baslangic</span>
                        <input class="ai-console-input" type="time" name="hafta_sonu_uyku_baslangic" value="{{ old('hafta_sonu_uyku_baslangic', $persona?->hafta_sonu_uyku_baslangic ?? '02:00') }}" placeholder="02:00">
                    </label>
                    <label>
                        <span class="ai-console-label">Hafta Sonu Bitis</span>
                        <input class="ai-console-input" type="time" name="hafta_sonu_uyku_bitis" value="{{ old('hafta_sonu_uyku_bitis', $persona?->hafta_sonu_uyku_bitis ?? '10:00') }}" placeholder="10:00">
                    </label>
                </div>
            </section>

            <section class="ai-console-panel">
                <div class="ai-console-panel__header">
                    <div>
                        <h2 class="ai-console-panel__title">Kisisel Kurallar</h2>
                        <p class="ai-console-panel__copy">Persona seviyesindeki yasakli konular ve zorunlu kurallar.</p>
                    </div>
                </div>

                <div class="ai-console-control-group">
                    <label>
                        <span class="ai-console-label">Persona Yasakli Konular</span>
                        <textarea class="ai-console-textarea" name="blocked_topics" rows="8" placeholder="Her satira bir yasakli konu yaz.">{{ old('blocked_topics', $blockedTopicsText) }}</textarea>
                    </label>
                    <label>
                        <span class="ai-console-label">Persona Zorunlu Kurallar</span>
                        <textarea class="ai-console-textarea" name="required_rules" rows="8" placeholder="Her satira bir zorunlu kural yaz.">{{ old('required_rules', $requiredRulesText) }}</textarea>
                    </label>
                </div>
            </section>
        </div>

        <div class="sticky bottom-4 z-10">
            <div class="ai-console-toolbar">
                <div class="flex flex-wrap justify-end gap-3">
                    <a href="{{ $backUrl }}" class="ai-console-button ai-console-button--ghost">{{ $cancelLabel }}</a>
                    <button class="ai-console-button ai-console-button--primary">{{ $submitLabel }}</button>
                </div>
            </div>
        </div>
</form>
