@extends('admin.layout.ana')

@section('baslik', $kategoriBilgisi['etiket'])

@section('icerik')
    @php
        $tamamlanmaOrani =
            ($ayarIstatistikleri['toplam'] ?? 0) > 0
                ? (int) round((($ayarIstatistikleri['dolu'] ?? 0) / $ayarIstatistikleri['toplam']) * 100)
                : 0;
        $modelSecenekleri = [
            'gemini_varsayilan_model' => [
                'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            ],
            'openai_varsayilan_model' => [
                'gpt-4.1-nano' => 'GPT-4.1 Nano',
                'gpt-4.1-mini' => 'GPT-4.1 Mini',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'gpt-4o' => 'GPT-4o',
            ],
            'varsayilan_ai_saglayici' => [
                'gemini' => 'Google Gemini',
                'openai' => 'OpenAI',
            ],
            'yedek_ai_saglayici' => [
                'gemini' => 'Google Gemini',
                'openai' => 'OpenAI',
            ],
        ];
        $dosyaAyarlari = [
            'uygulama_logosu',
            'flutter_logosu',
            'apple_private_key_path',
            'apns_sertifika_yolu',
            'google_play_service_account_path',
            'firebase_service_account_path',
        ];
    @endphp

    <div class="studio p-6"
        style="--studio-accent: {{ $kategoriBilgisi['accent'] }}; --studio-accent-rgb: {{ $kategoriBilgisi['accent_rgb'] }}; --studio-accent-soft: {{ $kategoriBilgisi['accent_soft'] }}; --studio-hero-from: {{ $kategoriBilgisi['hero_from'] }}; --studio-hero-via: {{ $kategoriBilgisi['hero_via'] }}; --studio-hero-to: {{ $kategoriBilgisi['hero_to'] }};">
        <section class="studio-hero">
            <div class="studio-hero__inner lg:grid-cols-[minmax(0,1.7fr)_minmax(280px,0.95fr)] lg:items-end">
                <div>
                    <p class="studio-eyebrow">{{ $kategoriBilgisi['kicker'] }}</p>
                    <h2 class="studio-heading">{{ $kategoriBilgisi['etiket'] }}</h2>
                    @if (!empty($kategoriBilgisi['aciklama']))
                        <p class="studio-copy">{{ $kategoriBilgisi['aciklama'] }}</p>
                    @endif

                    <div class="studio-chipbar">
                        <span class="studio-chip">{{ $kategoriBilgisi['sidebar_grup'] }}</span>
                        <span class="studio-chip">{{ $ayarIstatistikleri['toplam'] }} alan</span>
                        <span class="studio-chip">{{ $ayarIstatistikleri['dolu'] }} dolu</span>
                    </div>
                </div>

                <div class="studio-panelstack sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                    <div class="studio-panel">
                        <p class="studio-panel__meta">Tamamlanma</p>
                        <p class="studio-panel__title">%{{ $tamamlanmaOrani }}</p>
                    </div>
                    <div class="studio-panel">
                        <p class="studio-panel__meta">Dosya alanı</p>
                        <p class="studio-panel__title">{{ $ayarIstatistikleri['dosya'] }} adet</p>
                    </div>
                    <div class="studio-panel">
                        <p class="studio-panel__meta">Durum akışı</p>
                        <p class="studio-panel__title">{{ $ayarIstatistikleri['otomasyon'] }} otomasyon</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_340px]">
            <form method="POST" action="{{ route('admin.ayarlar.kategori.guncelle', ['kategori' => $aktifKategori]) }}"
                enctype="multipart/form-data" class="studio-main space-y-6">
                @csrf
                @method('PUT')

                @include('admin.partials.form-hatalari')

                @include('admin.ayarlar.partials.grup-karti', [
                    'gKod' => $aktifKategori,
                    'gAd' => $kategoriBilgisi['etiket'],
                    'grupAciklamasi' => $kategoriBilgisi['aciklama'],
                    'ayarListesi' => $ayarlar,
                    'modelSecenekleri' => $modelSecenekleri,
                    'dosyaAyarlari' => $dosyaAyarlari,
                ])

                <section class="studio-card">
                    <div class="studio-actions">
                        <div>
                            <p class="studio-kicker">Yayinla</p>
                            <h3 class="studio-title">Kategoriyi kaydet</h3>
                            <p class="studio-description">Degisiklikler sadece bu kategoriye ait ayar anahtarlarina
                                uygulanir.</p>
                        </div>
                        <div class="studio-actions__buttons">
                            <button type="submit" class="studio-button studio-button--primary">Degisiklikleri
                                kaydet</button>
                        </div>
                    </div>
                </section>
            </form>

            <aside class="studio-sidebar space-y-6">
                <section class="studio-card">
                    <p class="studio-kicker">Ayni Grup</p>
                    <h3 class="studio-title">Bagli kategoriler</h3>
                    <p class="studio-description">Bu kategoriyle ayni panel grubunda yer alan ayarlara hizli gecis
                        yapabilirsin.</p>

                    <nav class="studio-nav mt-4">
                        @foreach ($ayniGrupKategoriler as $ilgiliKategori)
                            <a href="{{ route('admin.ayarlar.kategori', ['kategori' => $ilgiliKategori['slug']]) }}"
                                class="studio-nav__link"
                                @if ($ilgiliKategori['slug'] === $aktifKategori) style="border-color: rgba({{ $kategoriBilgisi['accent_rgb'] }}, .35); color: {{ $kategoriBilgisi['accent'] }}; background: linear-gradient(180deg, rgba({{ $kategoriBilgisi['accent_rgb'] }}, .12), rgba(255,255,255,.98)); box-shadow: 0 18px 30px -28px rgba({{ $kategoriBilgisi['accent_rgb'] }}, .8);" @endif>
                                <span>{{ $ilgiliKategori['etiket'] }}</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25 15 12l-6 6.75" />
                                </svg>
                            </a>
                        @endforeach
                    </nav>
                </section>

                <section class="studio-card">
                    <p class="studio-kicker">Operasyon Ozeti</p>
                    <div class="studio-meta mt-4 sm:grid-cols-2 xl:grid-cols-1">
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Toplam alan</p>
                            <p class="studio-meta__value">{{ $ayarIstatistikleri['toplam'] }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Dolu alan</p>
                            <p class="studio-meta__value">{{ $ayarIstatistikleri['dolu'] }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Dosya ayari</p>
                            <p class="studio-meta__value">{{ $ayarIstatistikleri['dosya'] }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Boolean akisi</p>
                            <p class="studio-meta__value">{{ $ayarIstatistikleri['otomasyon'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="studio-card">
                    <p class="studio-kicker">Yonetim Notu</p>
                    <div class="studio-progress-list mt-4">
                        <div class="studio-progress">
                            <div class="studio-progress__top">
                                <span class="studio-progress__label">Kategori tamamlanma seviyesi</span>
                                <span class="studio-progress__value">%{{ $tamamlanmaOrani }}</span>
                            </div>
                            <div class="studio-progress__track">
                                <div class="studio-progress__fill" style="width: {{ $tamamlanmaOrani }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="studio-copy-block">Bu ekran panel konseptiyle uyumlu olarak tek kategoriye odaklanir. Kritik
                        alanlari once doldurup sonra ayni grup icindeki diger kategorilere gecmek operasyon akisini
                        hizlandirir.</div>
                </section>

                @if ($odemeDurumKarti)
                    <section class="studio-card">
                        <p class="studio-kicker">Odeme Kanali</p>
                        <h3 class="studio-title">{{ $odemeDurumKarti['platform'] }} durumu</h3>
                        <p class="studio-description">{{ $odemeDurumKarti['not'] }}</p>

                        <div class="studio-meta mt-4 sm:grid-cols-2 xl:grid-cols-1">
                            <div class="studio-meta__item">
                                <p class="studio-meta__eyebrow">Kanal</p>
                                <p class="studio-meta__value">{{ $odemeDurumKarti['aktif'] ? 'Acik' : 'Kapali' }}</p>
                            </div>
                            <div class="studio-meta__item">
                                <p class="studio-meta__eyebrow">Hazirlik</p>
                                <p class="studio-meta__value">{{ $odemeDurumKarti['hazir'] ? 'Hazir' : 'Eksik' }}</p>
                            </div>
                            <div class="studio-meta__item">
                                <p class="studio-meta__eyebrow">Aktif puan paketi</p>
                                <p class="studio-meta__value">{{ $odemeDurumKarti['paketler']['puan'] }}</p>
                            </div>
                            <div class="studio-meta__item">
                                <p class="studio-meta__eyebrow">Aktif abonelik</p>
                                <p class="studio-meta__value">{{ $odemeDurumKarti['paketler']['abonelik'] }}</p>
                            </div>
                        </div>

                        @if (!$odemeDurumKarti['hazir'])
                            <div class="studio-copy-block mt-4">
                                Eksik alanlar: {{ implode(', ', $odemeDurumKarti['eksikAlanlar']) }}
                            </div>
                        @endif

                        @if (!$odemeDurumKarti['aktif'])
                            <div class="studio-copy-block mt-4">
                                Bu kanal panelde kapali oldugu icin mobil uygulama paket listesi bos donecek ve satin alma
                                dogrulamasi reddedilecek.
                            </div>
                        @endif
                    </section>
                @endif

                @if ($aktifKategori === 'depolama')
                    <section class="studio-card">
                        <p class="studio-kicker">Nginx Senkron</p>
                        <h3 class="studio-title">Tek tikla uygula</h3>
                        <p class="studio-description">Paneldeki nginx upload limitini config dosyasina uygular. Opsiyonel
                            olarak
                            reload da deneyebilirsin.</p>

                        <form method="POST" action="{{ route('admin.ayarlar.depolama.nginx-limit-uygula') }}"
                            class="mt-4 space-y-3">
                            @csrf

                            <label class="studio-toggle block">
                                <div class="studio-toggle__row">
                                    <div>
                                        <div class="studio-toggle__title">Nginx reload dene</div>
                                        <div class="studio-toggle__body">Sunucu ortaminda reload komutu tanimliysa
                                            degisikligi hemen
                                            etkinlestirmeye calisir.</div>
                                    </div>
                                    <div class="shrink-0">
                                        <input type="hidden" name="reload" value="0">
                                        <input type="checkbox" name="reload" value="1" class="studio-check">
                                    </div>
                                </div>
                            </label>

                            <button type="submit" class="studio-button studio-button--primary w-full">Nginx limitini
                                uygula</button>
                        </form>
                    </section>
                @endif
            </aside>
        </div>
    </div>
@endsection
