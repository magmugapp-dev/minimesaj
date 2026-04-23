@extends('admin.layout.ana')

@section('baslik', 'Yeni AI Influencer')

@section('icerik')
    @php
        $listeler = include resource_path('ai_kisilik_ton_stil_model_listeleri.php');
        $openAiModelleri = ['gpt-4.1-nano', 'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4o'];
        $sections = [
            ['id' => 'erisim', 'label' => 'Erişim'],
            ['id' => 'profil', 'label' => 'Profil'],
            ['id' => 'cekirdek', 'label' => 'AI'],
        ];
    @endphp

    <div class="studio studio--influencer" x-data="{ provider: '{{ old('saglayici_tipi', 'gemini') }}' }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('admin.influencer.index') }}" class="studio-button studio-button--ghost">Influencer listesi</a>
            <h1 class="text-2xl font-semibold text-slate-950">Yeni influencer hesabi</h1>
        </div>

        <div class="studio-grid studio-grid--create">
            <form method="POST" action="{{ route('admin.influencer.kaydet') }}" class="studio-main">
                @csrf

                @include('admin.partials.form-hatalari')

                <section id="erisim" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div>
                            <h3 class="studio-title">Panel ve Instagram</h3>
                        </div>
                    </div>

                    <div class="studio-form-grid studio-form-grid--2">
                        <div>
                            <label class="studio-label" for="kullanici_adi">Giriş kullanıcı adı</label>
                            <input id="kullanici_adi" type="text" name="kullanici_adi" value="{{ old('kullanici_adi') }}" required class="studio-input" placeholder="burcin_panel">
                        </div>
                        <div x-data="{ showPassword: false }">
                            <label class="studio-label" for="sifre">Sifre</label>
                            <div class="relative">
                                <input id="sifre" x-bind:type="showPassword ? 'text' : 'password'" name="sifre" required minlength="6" class="studio-input pr-12" placeholder="En az 6 karakter">
                                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-4 flex items-center text-slate-400 transition hover:text-slate-700">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="studio-label" for="instagram_kullanici_adi">Instagram kullanıcı adı</label>
                            <input id="instagram_kullanici_adi" type="text" name="instagram_kullanici_adi" value="{{ old('instagram_kullanici_adi') }}" required class="studio-input" placeholder="@burcinprofile">
                        </div>
                        <div>
                            <label class="studio-label" for="instagram_profil_id">Instagram profil ID</label>
                            <input id="instagram_profil_id" type="text" name="instagram_profil_id" value="{{ old('instagram_profil_id') }}" class="studio-input" placeholder="1784...">
                        </div>
                    </div>
                </section>

                <section id="profil" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div>
                            <h3 class="studio-title">Kimlik</h3>
                        </div>
                    </div>

                    <div class="studio-form-grid studio-form-grid--2">
                        <div>
                            <label class="studio-label" for="ad">Ad</label>
                            <input id="ad" type="text" name="ad" value="{{ old('ad') }}" required class="studio-input" placeholder="Örn. Burçin">
                        </div>
                        <div>
                            <label class="studio-label" for="soyad">Soyad</label>
                            <input id="soyad" type="text" name="soyad" value="{{ old('soyad') }}" class="studio-input" placeholder="Örn. Evci">
                        </div>
                        <div>
                            <label class="studio-label" for="cinsiyet">Cinsiyet</label>
                            <select id="cinsiyet" name="cinsiyet" required class="studio-select">
                                <option value="kadin" @selected(old('cinsiyet') === 'kadin')>Kadın</option>
                                <option value="erkek" @selected(old('cinsiyet') === 'erkek')>Erkek</option>
                                <option value="belirtmek_istemiyorum" @selected(old('cinsiyet') === 'belirtmek_istemiyorum')>Belirtmek istemiyorum</option>
                            </select>
                        </div>
                        <div>
                            <label class="studio-label" for="dogum_yili">Doğum yılı</label>
                            <input id="dogum_yili" type="number" name="dogum_yili" value="{{ old('dogum_yili') }}" min="1950" max="{{ now()->year }}" class="studio-input" placeholder="1998">
                        </div>
                        <div>
                            <label class="studio-label" for="ulke">Ülke</label>
                            <input id="ulke" type="text" name="ulke" value="{{ old('ulke', 'Türkiye') }}" class="studio-input" placeholder="Türkiye">
                        </div>
                        <div>
                            <label class="studio-label" for="il">İl</label>
                            <input id="il" type="text" name="il" value="{{ old('il') }}" class="studio-input" placeholder="İstanbul">
                        </div>
                        <div>
                            <label class="studio-label" for="ilce">İlçe</label>
                            <input id="ilce" type="text" name="ilce" value="{{ old('ilce') }}" class="studio-input" placeholder="Kadıköy">
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="studio-label" for="biyografi">Biyografi</label>
                        <textarea id="biyografi" name="biyografi" rows="4" class="studio-textarea" placeholder="Kısa profil özeti">{{ old('biyografi') }}</textarea>
                    </div>
                </section>

                <section id="cekirdek" class="studio-card scroll-mt-24">
                    <div class="studio-card__header">
                        <div>
                            <h3 class="studio-title">Karakter ve model</h3>
                        </div>
                    </div>

                    <div class="studio-choice-grid studio-choice-grid--2">
                        <label class="studio-choice">
                            <input type="radio" name="saglayici_tipi" value="gemini" x-model="provider">
                            <span class="studio-choice__card">
                                <span class="studio-choice__pill">Önerilen</span>
                                <span class="studio-choice__title">Google Gemini</span>
                            </span>
                        </label>

                        <label class="studio-choice">
                            <input type="radio" name="saglayici_tipi" value="openai" x-model="provider">
                            <span class="studio-choice__card">
                                <span class="studio-choice__pill">Seçenek</span>
                                <span class="studio-choice__title">OpenAI</span>
                            </span>
                        </label>
                    </div>

                    <div class="studio-form-grid studio-form-grid--2 mt-6">
                        <div>
                            <label class="studio-label" for="model_adi_openai">Model</label>
                            <input type="hidden" name="model_adi" value="gemini-2.5-flash" x-bind:disabled="provider !== 'gemini'">
                            <select id="model_adi_openai" name="model_adi" class="studio-select" x-bind:disabled="provider !== 'openai'">
                                @foreach ($openAiModelleri as $model)
                                    <option value="{{ $model }}" @selected(old('model_adi', 'gpt-4.1-mini') === $model)>{{ $model }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="studio-label" for="kisilik_tipi">Kişilik tipi</label>
                            <select id="kisilik_tipi" name="kisilik_tipi" class="studio-select">
                                <option value="">Seçiniz...</option>
                                @foreach ($listeler['kisilik_tipleri'] as $secenek)
                                    <option value="{{ $secenek }}" @selected(old('kisilik_tipi') === $secenek)>{{ $secenek }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="studio-label" for="konusma_tonu">Konuşma tonu</label>
                            <select id="konusma_tonu" name="konusma_tonu" class="studio-select">
                                <option value="">Seçiniz...</option>
                                @foreach ($listeler['konusma_tonlari'] as $secenek)
                                    <option value="{{ $secenek }}" @selected(old('konusma_tonu') === $secenek)>{{ $secenek }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="studio-label" for="konusma_stili">Konuşma stili</label>
                            <select id="konusma_stili" name="konusma_stili" class="studio-select">
                                <option value="">Seçiniz...</option>
                                @foreach ($listeler['konusma_stilleri'] as $secenek)
                                    <option value="{{ $secenek }}" @selected(old('konusma_stili') === $secenek)>{{ $secenek }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="studio-label" for="kisilik_aciklamasi">Kişilik açıklaması</label>
                        <textarea id="kisilik_aciklamasi" name="kisilik_aciklamasi" rows="4" class="studio-textarea" placeholder="Karakterin genel tavrını yazın">{{ old('kisilik_aciklamasi') }}</textarea>
                    </div>
                </section>

                <section class="studio-card">
                    <div class="studio-actions">
                        <h3 class="studio-title">Kaydet</h3>
                        <div class="studio-actions__buttons">
                            <a href="{{ route('admin.influencer.index') }}" class="studio-button studio-button--ghost">Vazgeç</a>
                            <button type="submit" class="studio-button studio-button--primary">Influencer hesabını oluştur</button>
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
                            <p class="studio-meta__eyebrow">Durum</p>
                            <p class="studio-meta__value">Aktif</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Varsayılan</p>
                            <p class="studio-meta__value">Gemini</p>
                        </div>
                    </div>
                </section>

                <section class="studio-card">
                    <div class="studio-stack mt-4">
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
