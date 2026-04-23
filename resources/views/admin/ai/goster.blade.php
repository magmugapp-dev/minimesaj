@extends('admin.layout.ana')

@section('baslik', $kullanici->ad . ' - AI Detay')

@section('icerik')
    @php
        $ayar = $kullanici->aiAyar;
        $tamAd = trim($kullanici->ad . ' ' . $kullanici->soyad);
        $durumPill = match ($kullanici->hesap_durumu) {
            'aktif' => 'studio-pill--success',
            'pasif' => 'studio-pill--warning',
            'yasakli' => 'studio-pill--danger',
            default => 'studio-pill--neutral',
        };
        $cinsiyet = match ($kullanici->cinsiyet) {
            'erkek' => 'Erkek',
            'kadin' => 'Kadın',
            'belirtmek_istemiyorum' => 'Belirtmek istemiyorum',
            default => '—',
        };
        $konum =
            collect([$kullanici->ilce, $kullanici->il, $kullanici->ulke])
                ->filter()
                ->implode(', ') ?:
            '—';
        $yasakliKonular = collect(
            is_array($ayar?->yasakli_konular)
                ? $ayar->yasakli_konular
                : preg_split('/\r\n|\r|\n/', (string) ($ayar?->yasakli_konular ?? '')),
        )->filter();
        $zorunluKurallar = collect(
            is_array($ayar?->zorunlu_kurallar)
                ? $ayar->zorunlu_kurallar
                : preg_split('/\r\n|\r|\n/', (string) ($ayar?->zorunlu_kurallar ?? '')),
        )->filter();
        $seviyeler = $ayar
            ? [
                ['etiket' => 'Emoji', 'deger' => $ayar->emoji_seviyesi],
                ['etiket' => 'Flört', 'deger' => $ayar->flort_seviyesi],
                ['etiket' => 'Girişkenlik', 'deger' => $ayar->giriskenlik_seviyesi],
                ['etiket' => 'Utangaçlık', 'deger' => $ayar->utangaclik_seviyesi],
                ['etiket' => 'Duygusallık', 'deger' => $ayar->duygusallik_seviyesi],
                ['etiket' => 'Kıskançlık', 'deger' => $ayar->kiskanclik_seviyesi],
                ['etiket' => 'Mizah', 'deger' => $ayar->mizah_seviyesi],
                ['etiket' => 'Zeka', 'deger' => $ayar->zeka_seviyesi],
            ]
            : [];
    @endphp

    <div class="studio studio--ai p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('admin.ai.index') }}" class="studio-button studio-button--ghost">AI listesi</a>
            <h1 class="text-2xl font-semibold text-slate-950">{{ $tamAd }}</h1>
        </div>

        @if (session('basari'))
            <div class="studio-notice studio-notice--success">{{ session('basari') }}</div>
        @endif

        @if (!$ayar)
            <section class="studio-card" style="margin-top: 1rem;">
                <div class="studio-actions">
                    <h3 class="studio-title">AI ayarı bulunmuyor</h3>
                    <div class="studio-actions__buttons">
                        <a href="{{ route('admin.ai.duzenle', $kullanici) }}"
                            class="studio-button studio-button--primary">Ayar oluştur</a>
                    </div>
                </div>
            </section>
        @else
            <div class="studio-grid studio-grid--detail">
                <div class="studio-main">
                    <section class="studio-stat-grid studio-stat-grid--4">
                        <div class="studio-stat">
                            <p class="studio-stat__label">Eşleşme</p>
                            <p class="studio-stat__value">{{ number_format($kullanici->eslesmeler_count) }}</p>
                        </div>
                        <div class="studio-stat">
                            <p class="studio-stat__label">Günlük limit</p>
                            <p class="studio-stat__value">{{ $ayar->gunluk_konusma_limiti ?: 'Sinirsiz' }}</p>
                        </div>
                    </section>

                    <section class="studio-card">
                        <div class="studio-card__header">
                            <div>
                                <h3 class="studio-title">Kullanıcı</h3>
                            </div>
                        </div>
                        <div class="studio-info-grid studio-info-grid--2">
                            <div class="studio-surface">
                                <p class="studio-surface__title">Kimlik</p>
                                <dl class="studio-data-list">
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Kullanıcı adı</dt>
                                        <dd class="studio-data-value">{{ '@' . $kullanici->kullanici_adi }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Cinsiyet</dt>
                                        <dd class="studio-data-value">{{ $cinsiyet }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Doğum yılı</dt>
                                        <dd class="studio-data-value">{{ $kullanici->dogum_yili ?? '—' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Konum</dt>
                                        <dd class="studio-data-value">{{ $konum }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Durum</dt>
                                        <dd class="studio-data-value">{{ ucfirst($kullanici->hesap_durumu) }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Kayıt</dt>
                                        <dd class="studio-data-value">{{ $kullanici->created_at?->format('d.m.Y H:i') }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="studio-surface">
                                <p class="studio-surface__title">Biyografi</p>
                                <div class="studio-copy-block">{{ $kullanici->biyografi ?: 'Biyografi girilmemiş.' }}</div>
                            </div>
                        </div>
                    </section>

                    <section class="studio-card">
                        <div class="studio-card__header">
                            <div>
                                <h3 class="studio-title">Ses ve karakter</h3>
                            </div>
                        </div>
                        <div class="studio-info-grid studio-info-grid--2">
                            <div class="studio-surface">
                                <p class="studio-surface__title">Seviyeler</p>
                                <div class="studio-progress-list" style="margin-top: 1rem;">
                                    @foreach ($seviyeler as $seviye)
                                        <div class="studio-progress">
                                            <div class="studio-progress__top">
                                                <span class="studio-progress__label">{{ $seviye['etiket'] }}</span>
                                                <span class="studio-progress__value">{{ $seviye['deger'] }}/10</span>
                                            </div>
                                            <div class="studio-progress__track">
                                                <div class="studio-progress__fill"
                                                    style="width: {{ $seviye['deger'] * 10 }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="studio-surface">
                                <p class="studio-surface__title">Tarz</p>
                                <dl class="studio-data-list">
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Kişilik tipi</dt>
                                        <dd class="studio-data-value">{{ $ayar->kisilik_tipi ?: '—' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Konuşma tonu</dt>
                                        <dd class="studio-data-value">{{ $ayar->konusma_tonu ?: '—' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Konuşma stili</dt>
                                        <dd class="studio-data-value">{{ $ayar->konusma_stili ?: '—' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Hafıza</dt>
                                        <dd class="studio-data-value">{{ $ayar->hafiza_aktif_mi ? 'Aktif' : 'Pasif' }}
                                        </dd>
                                    </div>
                                </dl>
                                <div class="studio-copy-block">
                                    {{ $ayar->kisilik_aciklamasi ?: 'Kişilik açıklaması girilmemiş.' }}</div>
                            </div>
                        </div>
                    </section>

                    <section class="studio-card">
                        <div class="studio-card__header">
                            <div>
                                <h3 class="studio-title">Model ve limitler</h3>
                            </div>
                        </div>
                        <div class="studio-info-grid studio-info-grid--2">
                            <div class="studio-surface">
                                <p class="studio-surface__title">Model</p>
                                <dl class="studio-data-list">
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Sağlayıcı</dt>
                                        <dd class="studio-data-value">{{ ucfirst($ayar->saglayici_tipi) }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Ana model</dt>
                                        <dd class="studio-data-value studio-data-value--code">{{ $ayar->model_adi }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Yedek</dt>
                                        <dd class="studio-data-value studio-data-value--code">
                                            {{ $ayar->yedek_model_adi ?: '—' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Temperature</dt>
                                        <dd class="studio-data-value">{{ $ayar->temperature }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Top P</dt>
                                        <dd class="studio-data-value">{{ $ayar->top_p }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Max token</dt>
                                        <dd class="studio-data-value">{{ number_format($ayar->max_output_tokens) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="studio-surface">
                                <p class="studio-surface__title">Mesaj ayarları</p>
                                <dl class="studio-data-list">
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Cevap süresi</dt>
                                        <dd class="studio-data-value">{{ $ayar->minimum_cevap_suresi_saniye ?? 0 }} -
                                            {{ $ayar->maksimum_cevap_suresi_saniye ?? 0 }} sn</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Mesaj uzunluğu</dt>
                                        <dd class="studio-data-value">{{ $ayar->mesaj_uzunlugu_min ?? '—' }} -
                                            {{ $ayar->mesaj_uzunlugu_max ?? '—' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Tek kullanıcı limiti</dt>
                                        <dd class="studio-data-value">
                                            {{ $ayar->tek_kullanici_gunluk_mesaj_limiti ?: 'Sınırsız' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">İlk mesaj</dt>
                                        <dd class="studio-data-value">{{ $ayar->ilk_mesaj_atar_mi ? 'Açık' : 'Kapalı' }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                        @if ($ayar->ilk_mesaj_sablonu)
                            <div class="studio-copy-block">{{ $ayar->ilk_mesaj_sablonu }}</div>
                        @endif
                    </section>

                    <section class="studio-card">
                        <div class="studio-card__header">
                            <div>
                                <h3 class="studio-title">Aktif saatler</h3>
                            </div>
                        </div>
                        <div class="studio-info-grid studio-info-grid--2">
                            <div class="studio-surface">
                                <p class="studio-surface__title">Pencere</p>
                                <dl class="studio-data-list">
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Saat dilimi</dt>
                                        <dd class="studio-data-value">{{ $ayar->saat_dilimi ?? 'Europe/Istanbul' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Hafta içi</dt>
                                        <dd class="studio-data-value">{{ $ayar->uyku_baslangic ?? '23:00' }} -
                                            {{ $ayar->uyku_bitis ?? '07:30' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Hafta sonu</dt>
                                        <dd class="studio-data-value">{{ $ayar->hafta_sonu_uyku_baslangic ?: '—' }} -
                                            {{ $ayar->hafta_sonu_uyku_bitis ?: '—' }}</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Rastgele gecikme</dt>
                                        <dd class="studio-data-value">{{ $ayar->rastgele_gecikme_dakika ?? 15 }} dk</dd>
                                    </div>
                                    <div class="studio-data-row">
                                        <dt class="studio-data-label">Cevrim ici zorunlulugu</dt>
                                        <dd class="studio-data-value">{{ $kullanici->cevrim_ici_mi ? 'Su an uygun' : 'Cevrim disi ise cevap bekler' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="studio-surface">
                                <p class="studio-surface__title">Kurallar</p>
                                @if ($yasakliKonular->isNotEmpty())
                                    <div class="studio-pill-list">
                                        @foreach ($yasakliKonular as $konu)
                                            <span class="studio-pill studio-pill--danger">{{ $konu }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($zorunluKurallar->isNotEmpty())
                                    <div class="studio-pill-list" style="margin-top: .85rem;">
                                        @foreach ($zorunluKurallar as $kural)
                                            <span class="studio-pill studio-pill--success">{{ $kural }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($yasakliKonular->isEmpty() && $zorunluKurallar->isEmpty())
                                    <div class="studio-copy-block">Ek kural tanımlanmamış.</div>
                                @endif
                            </div>
                        </div>

                        @if ($ayar->sistem_komutu)
                            <pre class="studio-code">{{ $ayar->sistem_komutu }}</pre>
                        @endif
                    </section>
                </div>

                <aside class="studio-sidebar">
                    <section class="studio-card">
                        <div class="studio-meta mt-4">
                            <div class="studio-meta__item">
                                <p class="studio-meta__eyebrow">Hesap</p>
                                <p class="studio-meta__value">{{ ucfirst($kullanici->hesap_durumu) }}</p>
                            </div>
                            <div class="studio-meta__item">
                                <p class="studio-meta__eyebrow">AI</p>
                                <p class="studio-meta__value">{{ $ayar->aktif_mi ? 'Aktif' : 'Pasif' }}</p>
                            </div>
                            <div class="studio-meta__item">
                                <p class="studio-meta__eyebrow">Model</p>
                                <p class="studio-meta__value">{{ $ayar->model_adi }}</p>
                            </div>
                            <div class="studio-meta__item">
                                <p class="studio-meta__eyebrow">Hafıza</p>
                                <p class="studio-meta__value">{{ $ayar->hafiza_aktif_mi ? 'Aktif' : 'Pasif' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="studio-card">
                        <div class="studio-pill-list mt-4">
                            <span class="studio-pill {{ $durumPill }}">{{ ucfirst($kullanici->hesap_durumu) }}</span>
                            <span
                                class="studio-pill {{ $ayar->aktif_mi ? 'studio-pill--success' : 'studio-pill--neutral' }}">{{ $ayar->aktif_mi ? 'AI aktif' : 'AI pasif' }}</span>
                            <span
                                class="studio-pill {{ $ayar->hafiza_aktif_mi ? 'studio-pill--info' : 'studio-pill--neutral' }}">{{ $ayar->hafiza_aktif_mi ? 'Hafıza açık' : 'Hafıza kapalı' }}</span>
                        </div>
                    </section>

                    <section class="studio-card">
                        <div class="studio-stack mt-4">
                            <a href="{{ route('admin.ai.duzenle', $kullanici) }}" class="studio-linkcard">
                                <span>Düzenle</span>
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
        @endif
    </div>
@endsection
