@extends('admin.layout.ana')

@section('baslik', 'İstatistik & Raporlama')

@section('icerik')
    @php
        $toplamHesap = $kullanici['toplam'] + $kullanici['ai'];
        $aktifOrani = $toplamHesap > 0 ? round(($kullanici['aktif'] / $toplamHesap) * 100) : 0;
        $premiumOrani = $kullanici['toplam'] > 0 ? round(($kullanici['premium'] / $kullanici['toplam']) * 100) : 0;
        $eslesmeTamamlanmaOrani = $eslesme['toplam'] > 0 ? round(($eslesme['bitti'] / $eslesme['toplam']) * 100) : 0;
        $aiMesajOrani = $mesaj['toplam'] > 0 ? round(($mesaj['ai_uretilmis'] / $mesaj['toplam']) * 100) : 0;
        $instagramBaglantiOrani =
            $instagram['hesap_toplam'] > 0 ? round(($instagram['hesap_bagli'] / $instagram['hesap_toplam']) * 100) : 0;
        $aiGorevBasariOrani =
            $instagram['ai_gorev_toplam'] > 0
                ? round(($instagram['ai_gorev_basarili'] / $instagram['ai_gorev_toplam']) * 100)
                : 0;
        $odemeBasariOrani =
            $finansal['toplam_islem'] > 0 ? round(($finansal['basarili_islem'] / $finansal['toplam_islem']) * 100) : 0;
        $iosGelirOrani =
            $finansal['toplam_gelir'] > 0 ? round(($finansal['ios_gelir'] / $finansal['toplam_gelir']) * 100) : 0;
        $androidGelirOrani =
            $finansal['toplam_gelir'] > 0 ? round(($finansal['android_gelir'] / $finansal['toplam_gelir']) * 100) : 0;

        $trendKartlari = [
            [
                'label' => 'Kayıtlar',
                'values' => $trendler['kayitlar'],
                'class' => 'stats-trend-card--indigo',
                'total' => number_format(array_sum($trendler['kayitlar'])),
                'meta' => '7 gün',
                'money' => false,
            ],
            [
                'label' => 'Eşleşmeler',
                'values' => $trendler['eslesmeler'],
                'class' => 'stats-trend-card--rose',
                'total' => number_format(array_sum($trendler['eslesmeler'])),
                'meta' => '7 gün',
                'money' => false,
            ],
            [
                'label' => 'Mesajlar',
                'values' => $trendler['mesajlar'],
                'class' => 'stats-trend-card--emerald',
                'total' => number_format(array_sum($trendler['mesajlar'])),
                'meta' => '7 gün',
                'money' => false,
            ],
            [
                'label' => 'Gelir',
                'values' => $trendler['gelir'],
                'class' => 'stats-trend-card--amber',
                'total' => '₺' . number_format(array_sum($trendler['gelir']), 0, ',', '.'),
                'meta' => '7 gün',
                'money' => true,
            ],
        ];

        $operasyonListesi = [
            [
                'avatar' => 'KU',
                'title' => 'Kullanıcı havuzu',
                'sub' =>
                    number_format($kullanici['aktif']) .
                    ' aktif · ' .
                    number_format($kullanici['cevrimici']) .
                    ' çevrimiçi',
                'status' => $aktifOrani >= 60 ? 'Stabil' : 'İzle',
                'class' => $aktifOrani >= 60 ? 'board-status--success' : 'board-status--warn',
            ],
            [
                'avatar' => 'ES',
                'title' => 'Eşleşme akışı',
                'sub' =>
                    number_format($eslesme['aktif']) . ' aktif · ' . number_format($eslesme['bekliyor']) . ' bekleyen',
                'status' => $eslesmeTamamlanmaOrani >= 35 ? 'Sağlıklı' : 'İzle',
                'class' => $eslesmeTamamlanmaOrani >= 35 ? 'board-status--success' : 'board-status--info',
            ],
            [
                'avatar' => 'MD',
                'title' => 'Moderasyon',
                'sub' =>
                    number_format($moderasyon['sikayet_bekliyor']) .
                    ' bekleyen · ' .
                    number_format($moderasyon['engel_bugun']) .
                    ' bugün engel',
                'status' => $moderasyon['sikayet_bekliyor'] > 0 ? 'Dikkat' : 'Temiz',
                'class' => $moderasyon['sikayet_bekliyor'] > 0 ? 'board-status--warn' : 'board-status--success',
            ],
            [
                'avatar' => 'IG',
                'title' => 'Instagram',
                'sub' =>
                    number_format($instagram['hesap_bagli']) .
                    ' bağlı · ' .
                    number_format($instagram['mesaj_bugun']) .
                    ' bugün mesaj',
                'status' => $instagramBaglantiOrani >= 70 ? 'Online' : 'İzle',
                'class' => $instagramBaglantiOrani >= 70 ? 'board-status--success' : 'board-status--warn',
            ],
        ];
    @endphp

    <div class="board p-6">
        <div class="board-top">
            <section class="board-main">
                <div class="board-chipbar">
                    <span class="board-chip">
                        <span class="board-chip__dot"></span>
                        Canlı Rapor
                    </span>
                    <span class="board-chip">{{ now()->locale('tr')->isoFormat('D MMMM YYYY') }}</span>
                    <span class="board-chip">%{{ $aktifOrani }} aktif hesap</span>
                </div>

                <p class="board-kicker">İstatistik Merkezi</p>
                <h2 class="board-title">Platform nabzı tek ekranda</h2>
                <p class="board-total">{{ number_format($toplamHesap) }}</p>
                <p class="board-subtitle">Toplam hesap havuzu</p>

                <div class="board-main__stats">
                    <div class="board-mini">
                        <p class="board-mini__label">Bugün Kayıt</p>
                        <p class="board-mini__value">{{ number_format($kullanici['bugun']) }}</p>
                        <p class="board-mini__meta">Yeni kullanıcı açılışı</p>
                    </div>
                    <div class="board-mini">
                        <p class="board-mini__label">Aktif Eşleşme</p>
                        <p class="board-mini__value">{{ number_format($eslesme['aktif']) }}</p>
                        <p class="board-mini__meta">Canlı eşleşme akışı</p>
                    </div>
                    <div class="board-mini">
                        <p class="board-mini__label">Bugün Gelir</p>
                        <p class="board-mini__value">₺{{ number_format($finansal['bugun_gelir'], 0, ',', '.') }}</p>
                        <p class="board-mini__meta">Başarılı işlem tutarı</p>
                    </div>
                    <div class="board-mini">
                        <p class="board-mini__label">Bugün IG Mesaj</p>
                        <p class="board-mini__value">{{ number_format($instagram['mesaj_bugun']) }}</p>
                        <p class="board-mini__meta">Instagram günlük hacim</p>
                    </div>
                </div>
            </section>

            <section class="board-ring-card" style="--board-ring: {{ $aktifOrani }}; --board-ring-color: #22c55e;">
                <div class="board-ring-card__top">
                    <div>
                        <p class="board-card__label">Aktiflik</p>
                        <p class="board-panel__title" style="margin-top: .35rem;">Kullanıcı sağlığı</p>
                    </div>
                    <span
                        class="board-status {{ $aktifOrani >= 60 ? 'board-status--success' : 'board-status--warn' }}">{{ $aktifOrani >= 60 ? 'Stabil' : 'İzle' }}</span>
                </div>
                <div class="board-ring">
                    <div class="board-ring__inner">
                        <div class="board-ring__value">%{{ $aktifOrani }}</div>
                        <div class="board-ring__meta">Aktif</div>
                    </div>
                </div>
                <div class="board-ring-card__stats">
                    <div class="board-ring-card__row"><span>Premium oranı</span><strong>%{{ $premiumOrani }}</strong></div>
                    <div class="board-ring-card__row"><span>Çevrimiçi
                            kullanıcı</span><strong>{{ number_format($kullanici['cevrimici']) }}</strong></div>
                    <div class="board-ring-card__row"><span>Bu ay
                            kayıt</span><strong>{{ number_format($kullanici['bu_ay']) }}</strong></div>
                </div>
            </section>

            <section class="board-ring-card" style="--board-ring: {{ $aiGorevBasariOrani }}; --board-ring-color: #4f46e5;">
                <div class="board-ring-card__top">
                    <div>
                        <p class="board-card__label">AI Performans</p>
                        <p class="board-panel__title" style="margin-top: .35rem;">Görev başarısı</p>
                    </div>
                    <span
                        class="board-status {{ $aiGorevBasariOrani >= 80 ? 'board-status--success' : ($aiGorevBasariOrani >= 60 ? 'board-status--info' : 'board-status--warn') }}">{{ $aiGorevBasariOrani >= 80 ? 'Güçlü' : 'İzle' }}</span>
                </div>
                <div class="board-ring">
                    <div class="board-ring__inner">
                        <div class="board-ring__value">%{{ $aiGorevBasariOrani }}</div>
                        <div class="board-ring__meta">Başarı</div>
                    </div>
                </div>
                <div class="board-ring-card__stats">
                    <div class="board-ring-card__row"><span>Bağlı
                            hesap</span><strong>{{ number_format($instagram['hesap_bagli']) }}</strong></div>
                    <div class="board-ring-card__row"><span>Oto yanıt
                            açık</span><strong>{{ number_format($instagram['hesap_oto_yanit']) }}</strong></div>
                    <div class="board-ring-card__row"><span>AI görev
                            toplam</span><strong>{{ number_format($instagram['ai_gorev_toplam']) }}</strong></div>
                </div>
            </section>
        </div>

        <section class="board-metrics">
            <div class="board-stat board-stat--emerald">
                <div class="board-stat__top">
                    <div>
                        <p class="board-stat__label">Toplam Gelir</p>
                        <p class="board-stat__value">₺{{ number_format($finansal['toplam_gelir'], 0, ',', '.') }}</p>
                        <p class="board-stat__meta">Başarılı işlemlerden oluşan toplam gelir</p>
                    </div>
                    <div class="board-stat__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="board-stat board-stat--indigo">
                <div class="board-stat__top">
                    <div>
                        <p class="board-stat__label">Toplam Mesaj</p>
                        <p class="board-stat__value">{{ number_format($mesaj['toplam']) }}</p>
                        <p class="board-stat__meta">{{ number_format($mesaj['aktif_sohbet']) }} aktif sohbet ·
                            %{{ $aiMesajOrani }} AI üretim</p>
                    </div>
                    <div class="board-stat__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="board-stat board-stat--rose">
                <div class="board-stat__top">
                    <div>
                        <p class="board-stat__label">Eşleşme Hacmi</p>
                        <p class="board-stat__value">{{ number_format($eslesme['toplam']) }}</p>
                        <p class="board-stat__meta">{{ number_format($eslesme['bugun']) }} bugün ·
                            %{{ $eslesmeTamamlanmaOrani }} tamamlanma</p>
                    </div>
                    <div class="board-stat__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="board-stat board-stat--amber">
                <div class="board-stat__top">
                    <div>
                        <p class="board-stat__label">Premium Kullanıcı</p>
                        <p class="board-stat__value">{{ number_format($kullanici['premium']) }}</p>
                        <p class="board-stat__meta">%{{ $premiumOrani }} premium dönüşümü</p>
                    </div>
                    <div class="board-stat__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m8.99 14.664 4.88-8.112a.75.75 0 011.34.09l2.126 4.968 5.154.418a.75.75 0 01.427 1.308l-3.93 3.414 1.2 5.063a.75.75 0 01-1.119.81L15 20.25l-4.498 2.374a.75.75 0 01-1.083-.858l.975-5.016-3.707-3.49a.75.75 0 01.404-1.292l5.237-.463z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="board-stat board-stat--sky">
                <div class="board-stat__top">
                    <div>
                        <p class="board-stat__label">Instagram Mesaj</p>
                        <p class="board-stat__value">{{ number_format($instagram['mesaj_toplam']) }}</p>
                        <p class="board-stat__meta">{{ number_format($instagram['mesaj_bugun']) }} bugün ·
                            %{{ $instagramBaglantiOrani }} bağlılık</p>
                    </div>
                    <div class="board-stat__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="board-stat board-stat--violet">
                <div class="board-stat__top">
                    <div>
                        <p class="board-stat__label">Bekleyen Şikayet</p>
                        <p class="board-stat__value">{{ number_format($moderasyon['sikayet_bekliyor']) }}</p>
                        <p class="board-stat__meta">{{ number_format($moderasyon['engel_toplam']) }} toplam engel ·
                            {{ number_format($moderasyon['engel_bugun']) }} bugün</p>
                    </div>
                    <div class="board-stat__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm9-3.758c0 4.971-4.029 9-9 9s-9-4.029-9-9 4.029-9 9-9 9 4.029 9 9Z" />
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        <div class="board-bottom">
            <section class="board-panel">
                <div class="board-panel__head">
                    <div>
                        <p class="board-panel__eyebrow">Trendler</p>
                        <h3 class="board-panel__title">Son 7 gün</h3>
                    </div>
                    <span class="board-status board-status--info">Güncel</span>
                </div>

                <div class="stats-trend-grid">
                    @foreach ($trendKartlari as $kart)
                        @php $maksimum = max(1, max($kart['values'])); @endphp
                        <div class="stats-trend-card {{ $kart['class'] }}">
                            <div class="stats-trend-head">
                                <div>
                                    <p class="stats-trend-label">{{ $kart['label'] }}</p>
                                    <p class="stats-trend-total">{{ $kart['total'] }}</p>
                                </div>
                                <span class="stats-trend-meta">{{ $kart['meta'] }}</span>
                            </div>

                            <div class="stats-trend-bars">
                                @foreach ($kart['values'] as $indeks => $deger)
                                    <div class="stats-trend-col">
                                        <div class="stats-trend-bar"
                                            style="height: {{ max(10, ($deger / $maksimum) * 132) }}px;"></div>
                                        <div class="stats-trend-value">
                                            {{ $kart['money'] ? '₺' . number_format($deger, 0, ',', '.') : number_format($deger) }}
                                        </div>
                                        <div class="stats-trend-day">{{ $trendler['tarihler'][$indeks] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="board-panel">
                <div class="board-panel__head">
                    <div>
                        <p class="board-panel__eyebrow">Performans</p>
                        <h3 class="board-panel__title">Temel oranlar</h3>
                    </div>
                    <span class="board-status board-status--success">Özet</span>
                </div>

                <div class="board-progress-list">
                    <div class="board-progress-card">
                        <div class="board-progress__top">
                            <span class="board-progress__label">Aktif hesap oranı</span>
                            <span class="board-progress__value">%{{ $aktifOrani }}</span>
                        </div>
                        <div class="board-progress__track">
                            <div class="board-progress__fill" style="width: {{ $aktifOrani }}%"></div>
                        </div>
                    </div>
                    <div class="board-progress-card">
                        <div class="board-progress__top">
                            <span class="board-progress__label">Premium dönüşümü</span>
                            <span class="board-progress__value">%{{ $premiumOrani }}</span>
                        </div>
                        <div class="board-progress__track">
                            <div class="board-progress__fill" style="width: {{ $premiumOrani }}%"></div>
                        </div>
                    </div>
                    <div class="board-progress-card">
                        <div class="board-progress__top">
                            <span class="board-progress__label">AI mesaj oranı</span>
                            <span class="board-progress__value">%{{ $aiMesajOrani }}</span>
                        </div>
                        <div class="board-progress__track">
                            <div class="board-progress__fill" style="width: {{ $aiMesajOrani }}%"></div>
                        </div>
                    </div>
                    <div class="board-progress-card">
                        <div class="board-progress__top">
                            <span class="board-progress__label">Instagram bağlılık</span>
                            <span class="board-progress__value">%{{ $instagramBaglantiOrani }}</span>
                        </div>
                        <div class="board-progress__track">
                            <div class="board-progress__fill" style="width: {{ $instagramBaglantiOrani }}%"></div>
                        </div>
                    </div>
                    <div class="board-progress-card">
                        <div class="board-progress__top">
                            <span class="board-progress__label">Ödeme başarı oranı</span>
                            <span class="board-progress__value">%{{ $odemeBasariOrani }}</span>
                        </div>
                        <div class="board-progress__track">
                            <div class="board-progress__fill" style="width: {{ $odemeBasariOrani }}%"></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="board-panel">
                <div class="board-panel__head">
                    <div>
                        <p class="board-panel__eyebrow">Operasyon</p>
                        <h3 class="board-panel__title">Hızlı sağlık kontrolü</h3>
                    </div>
                    <span class="board-status board-status--info">Anlık</span>
                </div>

                <div class="board-list">
                    @foreach ($operasyonListesi as $satir)
                        <div class="board-list__item">
                            <div class="board-list__main">
                                <div class="board-list__avatar">{{ $satir['avatar'] }}</div>
                                <div>
                                    <div class="board-list__title">{{ $satir['title'] }}</div>
                                    <div class="board-list__sub">{{ $satir['sub'] }}</div>
                                </div>
                            </div>
                            <span class="board-status {{ $satir['class'] }}">{{ $satir['status'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="board-panel">
            <div class="board-panel__head">
                <div>
                    <p class="board-panel__eyebrow">Modüller</p>
                    <h3 class="board-panel__title">Kategori özetleri</h3>
                </div>
                <span class="board-status board-status--success">Tam görünüm</span>
            </div>

            <div class="board-actions-grid">
                <div class="board-action">
                    <div class="board-action__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="board-action__meta">Kullanıcılar</p>
                        <p class="board-action__title">Toplam kullanıcı havuzu</p>
                        <p class="board-action__value">{{ number_format($toplamHesap) }}</p>
                        <p class="board-action__meta">{{ number_format($kullanici['bugun']) }} bugün ·
                            {{ number_format($kullanici['bu_hafta']) }} bu hafta</p>
                    </div>
                </div>

                <div class="board-action">
                    <div class="board-action__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                        </svg>
                    </div>
                    <div>
                        <p class="board-action__meta">Eşleşmeler</p>
                        <p class="board-action__title">Eşleşme motoru</p>
                        <p class="board-action__value">{{ number_format($eslesme['toplam']) }}</p>
                        <p class="board-action__meta">{{ number_format($eslesme['rastgele']) }} rastgele ·
                            {{ number_format($eslesme['otomatik']) }} otomatik</p>
                    </div>
                </div>

                <div class="board-action">
                    <div class="board-action__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                        </svg>
                    </div>
                    <div>
                        <p class="board-action__meta">Mesajlar</p>
                        <p class="board-action__title">Sohbet hacmi</p>
                        <p class="board-action__value">{{ number_format($mesaj['toplam']) }}</p>
                        <p class="board-action__meta">{{ number_format($mesaj['metin']) }} metin ·
                            {{ number_format($mesaj['foto']) }} fotoğraf</p>
                    </div>
                </div>

                <div class="board-action">
                    <div class="board-action__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75" />
                        </svg>
                    </div>
                    <div>
                        <p class="board-action__meta">Finansal</p>
                        <p class="board-action__title">Tahsilat görünümü</p>
                        <p class="board-action__value">₺{{ number_format($finansal['toplam_gelir'], 0, ',', '.') }}</p>
                        <p class="board-action__meta">%{{ $iosGelirOrani }} iOS · %{{ $androidGelirOrani }} Android</p>
                    </div>
                </div>

                <div class="board-action">
                    <div class="board-action__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm9-3.758c0 4.971-4.029 9-9 9s-9-4.029-9-9 4.029-9 9-9 9 4.029 9 9Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="board-action__meta">Moderasyon</p>
                        <p class="board-action__title">Şikayet yönetimi</p>
                        <p class="board-action__value">{{ number_format($moderasyon['sikayet_toplam']) }}</p>
                        <p class="board-action__meta">{{ number_format($moderasyon['sikayet_inceleniyor']) }} inceleniyor
                            · {{ number_format($moderasyon['sikayet_cozuldu']) }} çözüldü</p>
                    </div>
                </div>

                <div class="board-action">
                    <div class="board-action__icon">
                        <svg style="width: 1.25rem; height: 1.25rem;" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069z" />
                        </svg>
                    </div>
                    <div>
                        <p class="board-action__meta">Instagram</p>
                        <p class="board-action__title">Hesap ve AI görevleri</p>
                        <p class="board-action__value">{{ number_format($instagram['hesap_toplam']) }}</p>
                        <p class="board-action__meta">{{ number_format($instagram['mesaj_gelen']) }} gelen ·
                            {{ number_format($instagram['mesaj_ai']) }} AI mesaj</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
