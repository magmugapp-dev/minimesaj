@extends('admin.layout.ana')

@section('baslik', $kullanici->ad . ' - AI Influencer Detay')

@section('icerik')
    @php
        $ayar = $kullanici->aiAyar;
        $igHesap = $kullanici->instagramHesaplari->first();
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
        $konum = collect([$kullanici->ilce, $kullanici->il, $kullanici->ulke])->filter()->implode(', ') ?: '—';
        $toplamMesaj = collect($instagramIstatistikleri)->sum('mesaj_sayisi');
        $toplamGorev = collect($instagramIstatistikleri)->sum('gorev_sayisi');
        $toplamKisi = collect($instagramIstatistikleri)->sum('kisi_sayisi');
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

    <div class="studio studio--influencer">
        <section class="studio-hero">
            <div class="studio-hero__inner">
                <div>
                    <a href="{{ route('admin.influencer.index') }}" class="studio-back">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Influencer listesi
                    </a>
                    <p class="studio-eyebrow">Influencer Profile</p>
                    <h2 class="studio-heading">{{ $tamAd }}</h2>
                    <div class="studio-chipbar">
                        <span class="studio-chip">{{ '@' . $kullanici->kullanici_adi }}</span>
                        <span class="studio-chip">{{ $igHesap?->instagram_kullanici_adi ? '@' . ltrim($igHesap->instagram_kullanici_adi, '@') : 'Instagram yok' }}</span>
                        <span class="studio-chip">{{ $ayar?->aktif_mi ? 'AI aktif' : 'AI pasif' }}</span>
                    </div>
                </div>

                <div class="studio-panelstack">
                    <div class="studio-panel">
                        <p class="studio-panel__meta">Durum</p>
                        <p class="studio-panel__title">{{ ucfirst($kullanici->hesap_durumu) }}</p>
                    </div>
                    <div class="studio-panel">
                        <p class="studio-panel__meta">Model</p>
                        <p class="studio-panel__title">{{ $ayar?->model_adi ?? 'Tanımsız' }}</p>
                    </div>
                    <div class="studio-panel">
                        <p class="studio-panel__meta">Instagram</p>
                        <p class="studio-panel__title">{{ $kullanici->instagramHesaplari->count() }} hesap</p>
                    </div>
                </div>
            </div>
        </section>

        @if (session('basari'))
            <div class="studio-notice studio-notice--success">{{ session('basari') }}</div>
        @endif

        <div class="studio-grid studio-grid--detail">
            <div class="studio-main">
                <section class="studio-stat-grid studio-stat-grid--4">
                    <div class="studio-stat">
                        <p class="studio-stat__label">Eşleşme</p>
                        <p class="studio-stat__value">{{ number_format($kullanici->eslesmeler_count) }}</p>
                        <p class="studio-stat__meta">Toplam eşleşme</p>
                    </div>
                    <div class="studio-stat">
                        <p class="studio-stat__label">Beğeni</p>
                        <p class="studio-stat__value">{{ number_format($kullanici->begeniler_count) }}</p>
                        <p class="studio-stat__meta">Atılan beğeni</p>
                    </div>
                    <div class="studio-stat">
                        <p class="studio-stat__label">DM mesaj</p>
                        <p class="studio-stat__value">{{ number_format($toplamMesaj) }}</p>
                        <p class="studio-stat__meta">Instagram mesajları</p>
                    </div>
                    <div class="studio-stat">
                        <p class="studio-stat__label">AI görev</p>
                        <p class="studio-stat__value">{{ number_format($toplamGorev) }}</p>
                        <p class="studio-stat__meta">Toplam görev</p>
                    </div>
                </section>

                <section class="studio-card">
                    <div class="studio-card__header">
                        <div><p class="studio-kicker">Profil</p><h3 class="studio-title">Kullanıcı</h3></div>
                    </div>
                    <div class="studio-info-grid studio-info-grid--2">
                        <div class="studio-surface">
                            <p class="studio-surface__title">Kimlik</p>
                            <dl class="studio-data-list">
                                <div class="studio-data-row"><dt class="studio-data-label">Kullanıcı adı</dt><dd class="studio-data-value">{{ '@' . $kullanici->kullanici_adi }}</dd></div>
                                <div class="studio-data-row"><dt class="studio-data-label">Cinsiyet</dt><dd class="studio-data-value">{{ $cinsiyet }}</dd></div>
                                <div class="studio-data-row"><dt class="studio-data-label">Doğum yılı</dt><dd class="studio-data-value">{{ $kullanici->dogum_yili ?? '—' }}</dd></div>
                                <div class="studio-data-row"><dt class="studio-data-label">Konum</dt><dd class="studio-data-value">{{ $konum }}</dd></div>
                                <div class="studio-data-row"><dt class="studio-data-label">Durum</dt><dd class="studio-data-value">{{ ucfirst($kullanici->hesap_durumu) }}</dd></div>
                                <div class="studio-data-row"><dt class="studio-data-label">Kayıt</dt><dd class="studio-data-value">{{ $kullanici->created_at?->format('d.m.Y H:i') }}</dd></div>
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
                        <div><p class="studio-kicker">AI</p><h3 class="studio-title">Karakter ve model</h3></div>
                    </div>

                    @if ($ayar)
                        <div class="studio-info-grid studio-info-grid--2">
                            <div class="studio-surface">
                                <p class="studio-surface__title">Model</p>
                                <dl class="studio-data-list">
                                    <div class="studio-data-row"><dt class="studio-data-label">AI durumu</dt><dd class="studio-data-value">{{ $ayar->aktif_mi ? 'Aktif' : 'Pasif' }}</dd></div>
                                    <div class="studio-data-row"><dt class="studio-data-label">Sağlayıcı</dt><dd class="studio-data-value">{{ ucfirst($ayar->saglayici_tipi) }}</dd></div>
                                    <div class="studio-data-row"><dt class="studio-data-label">Model</dt><dd class="studio-data-value studio-data-value--code">{{ $ayar->model_adi }}</dd></div>
                                    <div class="studio-data-row"><dt class="studio-data-label">Temperature</dt><dd class="studio-data-value">{{ $ayar->temperature }}</dd></div>
                                    <div class="studio-data-row"><dt class="studio-data-label">Top P</dt><dd class="studio-data-value">{{ $ayar->top_p }}</dd></div>
                                    <div class="studio-data-row"><dt class="studio-data-label">Max token</dt><dd class="studio-data-value">{{ number_format($ayar->max_output_tokens) }}</dd></div>
                                </dl>
                            </div>

                            <div class="studio-surface">
                                <p class="studio-surface__title">Tarz</p>
                                <dl class="studio-data-list">
                                    <div class="studio-data-row"><dt class="studio-data-label">Kişilik tipi</dt><dd class="studio-data-value">{{ $ayar->kisilik_tipi ?: '—' }}</dd></div>
                                    <div class="studio-data-row"><dt class="studio-data-label">Konuşma tonu</dt><dd class="studio-data-value">{{ $ayar->konusma_tonu ?: '—' }}</dd></div>
                                    <div class="studio-data-row"><dt class="studio-data-label">Konuşma stili</dt><dd class="studio-data-value">{{ $ayar->konusma_stili ?: '—' }}</dd></div>
                                    <div class="studio-data-row"><dt class="studio-data-label">İlk mesaj</dt><dd class="studio-data-value">{{ $ayar->ilk_mesaj_atar_mi ? 'Açık' : 'Kapalı' }}</dd></div>
                                </dl>
                                <div class="studio-copy-block">{{ $ayar->kisilik_aciklamasi ?: 'Kişilik açıklaması girilmemiş.' }}</div>
                            </div>
                        </div>

                        <div class="studio-surface" style="margin-top: 1rem;">
                            <p class="studio-surface__title">Seviyeler</p>
                            <div class="studio-progress-list" style="margin-top: 1rem;">
                                @foreach ($seviyeler as $seviye)
                                    <div class="studio-progress">
                                        <div class="studio-progress__top">
                                            <span class="studio-progress__label">{{ $seviye['etiket'] }}</span>
                                            <span class="studio-progress__value">{{ $seviye['deger'] }}/10</span>
                                        </div>
                                        <div class="studio-progress__track">
                                            <div class="studio-progress__fill" style="width: {{ $seviye['deger'] * 10 }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="studio-copy-block">AI ayarı bulunmuyor.</div>
                    @endif
                </section>

                <section class="studio-card">
                    <div class="studio-card__header">
                        <div><p class="studio-kicker">Instagram</p><h3 class="studio-title">Operasyon</h3></div>
                    </div>

                    @if ($kullanici->instagramHesaplari->isNotEmpty())
                        <div class="studio-stack">
                            @foreach ($kullanici->instagramHesaplari as $hesap)
                                @php
                                    $hesapPill = $hesap->aktif_mi ? 'studio-pill--success' : 'studio-pill--neutral';
                                    $hesapIstatistik = $instagramIstatistikleri[$hesap->id] ?? ['kisi_sayisi' => 0, 'mesaj_sayisi' => 0, 'gorev_sayisi' => 0];
                                @endphp
                                <div class="studio-surface">
                                    <div class="studio-actions">
                                        <div>
                                            <p class="studio-surface__title">Hesap</p>
                                            <p class="studio-title" style="font-size: 1.35rem; margin-top: .35rem;">{{ '@' . ltrim($hesap->instagram_kullanici_adi, '@') }}</p>
                                        </div>
                                        <div class="studio-actions__buttons">
                                            <span class="studio-pill {{ $hesapPill }}">{{ $hesap->aktif_mi ? 'Aktif' : 'Pasif' }}</span>
                                            <a href="{{ route('admin.instagram.goster', $hesap) }}" class="studio-button studio-button--ghost">Detay</a>
                                        </div>
                                    </div>

                                    <div class="studio-info-grid studio-info-grid--2" style="margin-top: 1rem;">
                                        <div>
                                            <dl class="studio-data-list">
                                                <div class="studio-data-row"><dt class="studio-data-label">Profil ID</dt><dd class="studio-data-value">{{ $hesap->instagram_profil_id ?: '—' }}</dd></div>
                                                <div class="studio-data-row"><dt class="studio-data-label">Otomatik cevap</dt><dd class="studio-data-value">{{ $hesap->otomatik_cevap_aktif_mi ? 'Açık' : 'Kapalı' }}</dd></div>
                                                <div class="studio-data-row"><dt class="studio-data-label">Yarı otomatik</dt><dd class="studio-data-value">{{ $hesap->yarim_otomatik_mod_aktif_mi ? 'Açık' : 'Kapalı' }}</dd></div>
                                                <div class="studio-data-row"><dt class="studio-data-label">Son bağlantı</dt><dd class="studio-data-value">{{ $hesap->son_baglanti_tarihi?->format('d.m.Y H:i') ?: '—' }}</dd></div>
                                            </dl>
                                        </div>

                                        <div>
                                            <dl class="studio-data-list">
                                                <div class="studio-data-row"><dt class="studio-data-label">Kişi</dt><dd class="studio-data-value">{{ number_format($hesapIstatistik['kisi_sayisi']) }}</dd></div>
                                                <div class="studio-data-row"><dt class="studio-data-label">Mesaj</dt><dd class="studio-data-value">{{ number_format($hesapIstatistik['mesaj_sayisi']) }}</dd></div>
                                                <div class="studio-data-row"><dt class="studio-data-label">AI görev</dt><dd class="studio-data-value">{{ number_format($hesapIstatistik['gorev_sayisi']) }}</dd></div>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="studio-copy-block">Instagram hesabı bulunmuyor.</div>
                    @endif
                </section>
            </div>

            <aside class="studio-sidebar">
                <section class="studio-card">
                    <p class="studio-kicker">Özet</p>
                    <div class="studio-meta mt-4">
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Hesap</p>
                            <p class="studio-meta__value">{{ ucfirst($kullanici->hesap_durumu) }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">AI</p>
                            <p class="studio-meta__value">{{ $ayar?->aktif_mi ? 'Aktif' : 'Pasif' }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Instagram</p>
                            <p class="studio-meta__value">{{ $igHesap?->instagram_kullanici_adi ?: 'Bağlı değil' }}</p>
                        </div>
                        <div class="studio-meta__item">
                            <p class="studio-meta__eyebrow">Kişi</p>
                            <p class="studio-meta__value">{{ number_format($toplamKisi) }}</p>
                        </div>
                    </div>
                </section>

                <section class="studio-card">
                    <p class="studio-kicker">Durum</p>
                    <div class="studio-pill-list mt-4">
                        <span class="studio-pill {{ $durumPill }}">{{ ucfirst($kullanici->hesap_durumu) }}</span>
                        <span class="studio-pill {{ $ayar?->aktif_mi ? 'studio-pill--success' : 'studio-pill--neutral' }}">{{ $ayar?->aktif_mi ? 'AI aktif' : 'AI pasif' }}</span>
                        <span class="studio-pill {{ $igHesap?->otomatik_cevap_aktif_mi ? 'studio-pill--info' : 'studio-pill--neutral' }}">{{ $igHesap?->otomatik_cevap_aktif_mi ? 'Oto yanıt açık' : 'Oto yanıt kapalı' }}</span>
                    </div>
                </section>

                <section class="studio-card">
                    <p class="studio-kicker">Aksiyonlar</p>
                    <div class="studio-stack mt-4">
                        <a href="{{ route('admin.influencer.duzenle', $kullanici) }}" class="studio-linkcard">
                            <span>Düzenle</span>
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
