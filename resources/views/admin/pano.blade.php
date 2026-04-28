@extends('admin.layout.ana')

@section('baslik', 'Pano')

@section('icerik')
    @php
        $toplamHesap = (int) $istatistikler['toplam_kullanici'] + (int) $istatistikler['toplam_ai'];
        $cevrimiciToplam = (int) $istatistikler['cevrimici_gercek'] + (int) $istatistikler['cevrimici_ai'];
        $aiOrani = $toplamHesap > 0 ? ($istatistikler['toplam_ai'] / $toplamHesap) * 100 : 0;
        $cevrimiciOran = $toplamHesap > 0 ? ($cevrimiciToplam / $toplamHesap) * 100 : 0;
        $bugunHacim = (int) $istatistikler['bugunun_kayitlari'] + (int) $istatistikler['bugunun_mesajlari'];
        $gelirMetni = 'TRY ' . number_format($istatistikler['toplam_gelir'], 2, ',', '.');
        $aiOraniMetni = '%' . number_format($aiOrani, 1, ',', '.');
        $cevrimiciOranMetni = '%' . number_format($cevrimiciOran, 1, ',', '.');
        $aiBar = min(100, round($aiOrani, 1));
        $cevrimiciBar = min(100, round($cevrimiciOran, 1));
    @endphp

    <div class="studio">
        <div class="board p-8">
            <section class="board-top">
                <article class="board-main">
                    <div class="board-chipbar">
                        <span class="board-chip">
                            <span class="board-chip__dot"></span>
                            live ops
                        </span>
                        <span class="board-chip">{{ number_format($bugunHacim) }} gunluk hareket</span>
                    </div>

                    <div class="board-kicker">MiniMesaj</div>
                    <h1 class="board-title">Pano</h1>
                    <div class="board-total">{{ number_format($toplamHesap) }}</div>
                    <div class="board-subtitle">toplam hesap</div>

                    <div class="board-main__stats">
                        <div class="board-mini">
                            <div class="board-mini__label">Gercek</div>
                            <div class="board-mini__value">{{ number_format($istatistikler['toplam_kullanici']) }}</div>
                            <div class="board-mini__meta">user</div>
                        </div>

                        <div class="board-mini">
                            <div class="board-mini__label">AI</div>
                            <div class="board-mini__value">{{ number_format($istatistikler['toplam_ai']) }}</div>
                            <div class="board-mini__meta">profil</div>
                        </div>

                        <div class="board-mini">
                            <div class="board-mini__label">Canli</div>
                            <div class="board-mini__value">{{ number_format($cevrimiciToplam) }}</div>
                            <div class="board-mini__meta">aktif</div>
                        </div>

                        <div class="board-mini">
                            <div class="board-mini__label">Eslesme</div>
                            <div class="board-mini__value">{{ number_format($istatistikler['aktif_eslesmeler']) }}</div>
                            <div class="board-mini__meta">aktif</div>
                        </div>
                    </div>
                </article>

                <article class="board-ring-card">
                    <div class="board-ring-card__top">
                        <div class="board-card__label">Canli oran</div>
                        <div class="board-card__meta">{{ number_format($cevrimiciToplam) }} hesap</div>
                    </div>

                    <div class="board-ring" style="--board-ring: {{ $cevrimiciBar }}; --board-ring-color: #22c55e;">
                        <div class="board-ring__inner">
                            <div class="board-ring__value">{{ $cevrimiciOranMetni }}</div>
                            <div class="board-ring__meta">online</div>
                        </div>
                    </div>

                    <div class="board-ring-card__stats">
                        <div class="board-ring-card__row">
                            <span>AI payi</span>
                            <strong>{{ $aiOraniMetni }}</strong>
                        </div>
                        <div class="board-ring-card__row">
                            <span>AI karakter</span>
                            <strong>{{ number_format($istatistikler['toplam_ai']) }}</strong>
                        </div>
                    </div>
                </article>

                <div class="board-stack">
                    <article class="board-card board-card--emerald">
                        <div class="board-card__label">Gelir</div>
                        <div class="board-card__value">{{ $gelirMetni }}</div>
                        <div class="board-card__meta">basarili odemeler</div>
                        <div class="board-card__trend">{{ number_format($istatistikler['bugunun_mesajlari']) }} mesaj</div>
                    </article>

                    <article class="board-card board-card--rose">
                        <div class="board-card__label">Moderasyon</div>
                        <div class="board-card__value">{{ number_format($istatistikler['bekleyen_sikayetler']) }}</div>
                        <div class="board-card__meta">bekleyen sikayet</div>
                        <div class="board-card__trend">{{ number_format($istatistikler['aktif_eslesmeler']) }} aktif
                            eslesme</div>
                    </article>
                </div>
            </section>

            <section class="board-metrics">
                <article class="board-stat board-stat--indigo">
                    <div class="board-stat__top">
                        <div class="board-stat__label">Bugun kayit</div>
                        <div class="board-stat__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5" />
                            </svg>
                        </div>
                    </div>
                    <div class="board-stat__value">{{ number_format($istatistikler['bugunun_kayitlari']) }}</div>
                    <div class="board-stat__meta">yeni hesap</div>
                </article>

                <article class="board-stat board-stat--violet">
                    <div class="board-stat__top">
                        <div class="board-stat__label">Bugun mesaj</div>
                        <div class="board-stat__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 10h10M7 14h6m8 6-4-2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="board-stat__value">{{ number_format($istatistikler['bugunun_mesajlari']) }}</div>
                    <div class="board-stat__meta">trafik</div>
                </article>

                <article class="board-stat board-stat--sky">
                    <div class="board-stat__top">
                        <div class="board-stat__label">Gercek user</div>
                        <div class="board-stat__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20 21a8 8 0 0 0-16 0m8-11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="board-stat__value">{{ number_format($istatistikler['toplam_kullanici']) }}</div>
                    <div class="board-stat__meta">hesap</div>
                </article>

                <article class="board-stat board-stat--emerald">
                    <div class="board-stat__top">
                        <div class="board-stat__label">AI profil</div>
                        <div class="board-stat__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.36 6.36-2.12-2.12M7.76 7.76 5.64 5.64m12.72 0-2.12 2.12M7.76 16.24l-2.12 2.12" />
                            </svg>
                        </div>
                    </div>
                    <div class="board-stat__value">{{ number_format($istatistikler['toplam_ai']) }}</div>
                    <div class="board-stat__meta">karakter</div>
                </article>

                <article class="board-stat board-stat--rose">
                    <div class="board-stat__top">
                        <div class="board-stat__label">Bekleyen</div>
                        <div class="board-stat__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m10.29 3.86-6.24 10.8A2 2 0 0 0 5.78 18h12.44a2 2 0 0 0 1.73-3.34l-6.22-10.8a2 2 0 0 0-3.44 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01" />
                            </svg>
                        </div>
                    </div>
                    <div class="board-stat__value">{{ number_format($istatistikler['bekleyen_sikayetler']) }}</div>
                    <div class="board-stat__meta">sikayet</div>
                </article>

                <article class="board-stat board-stat--amber">
                    <div class="board-stat__top">
                        <div class="board-stat__label">Eslesme</div>
                        <div class="board-stat__icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                            </svg>
                        </div>
                    </div>
                    <div class="board-stat__value">{{ number_format($istatistikler['aktif_eslesmeler']) }}</div>
                    <div class="board-stat__meta">aktif</div>
                </article>
            </section>

            <section class="board-bottom">
                <article class="board-panel">
                    <div class="board-panel__head">
                        <div>
                            <div class="board-panel__eyebrow">Ratios</div>
                            <div class="board-panel__title">Dagilim</div>
                        </div>
                    </div>

                    <div class="board-progress-list">
                        <div class="board-progress-card">
                            <div class="board-progress__top">
                                <span class="board-progress__label">AI payi</span>
                                <span class="board-progress__value">{{ $aiOraniMetni }}</span>
                            </div>
                            <div class="board-progress__track">
                                <div class="board-progress__fill" style="width: {{ $aiBar }}%;"></div>
                            </div>
                        </div>

                        <div class="board-progress-card">
                            <div class="board-progress__top">
                                <span class="board-progress__label">Canli oran</span>
                                <span class="board-progress__value">{{ $cevrimiciOranMetni }}</span>
                            </div>
                            <div class="board-progress__track">
                                <div class="board-progress__fill" style="width: {{ $cevrimiciBar }}%;"></div>
                            </div>
                        </div>

                    </div>
                </article>

                <article class="board-panel">
                    <div class="board-panel__head">
                        <div>
                            <div class="board-panel__eyebrow">Quick access</div>
                            <div class="board-panel__title">Kart menu</div>
                        </div>
                    </div>

                    <div class="board-actions-grid">
                        <a href="{{ route('admin.kullanicilar.index') }}" class="board-action">
                            <div class="board-action__icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M20 21a8 8 0 0 0-16 0m8-11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                                </svg>
                            </div>
                            <div>
                                <div class="board-action__title">Kullanicilar</div>
                                <div class="board-action__meta">user</div>
                                <div class="board-action__value">{{ number_format($istatistikler['toplam_kullanici']) }}
                                </div>
                            </div>
                        </a>

                        <a href="{{ route('admin.ai.index') }}" class="board-action">
                            <div class="board-action__icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.36 6.36-2.12-2.12M7.76 7.76 5.64 5.64m12.72 0-2.12 2.12M7.76 16.24l-2.12 2.12" />
                                </svg>
                            </div>
                            <div>
                                <div class="board-action__title">AI Studio</div>
                                <div class="board-action__meta">profil</div>
                                <div class="board-action__value">{{ number_format($istatistikler['toplam_ai']) }}</div>
                            </div>
                        </a>

                        <a href="{{ route('admin.moderasyon.sikayetler') }}" class="board-action">
                            <div class="board-action__icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 3 4 7v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V7l-8-4Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" />
                                </svg>
                            </div>
                            <div>
                                <div class="board-action__title">Moderasyon</div>
                                <div class="board-action__meta">queue</div>
                                <div class="board-action__value">
                                    {{ number_format($istatistikler['bekleyen_sikayetler']) }}</div>
                            </div>
                        </a>

                        <a href="{{ route('admin.eslesmeler.index') }}" class="board-action">
                            <div class="board-action__icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m8 12 3 3 5-6M4 12a8 8 0 1 1 16 0 8 8 0 0 1-16 0Z" />
                                </svg>
                            </div>
                            <div>
                                <div class="board-action__title">Eslesmeler</div>
                                <div class="board-action__meta">aktif</div>
                                <div class="board-action__value">{{ number_format($istatistikler['aktif_eslesmeler']) }}
                                </div>
                            </div>
                        </a>

                        <a href="{{ route('admin.finansal.odemeler') }}" class="board-action">
                            <div class="board-action__icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 6h18M7 3v6m10-6v6M6 21h12a2 2 0 0 0 2-2V8H4v11a2 2 0 0 0 2 2Z" />
                                </svg>
                            </div>
                            <div>
                                <div class="board-action__title">Finans</div>
                                <div class="board-action__meta">gelir</div>
                                <div class="board-action__value">{{ $gelirMetni }}</div>
                            </div>
                        </a>
                    </div>
                </article>

                <article class="board-panel">
                    <div class="board-panel__head">
                        <div>
                            <div class="board-panel__eyebrow">Moderasyon</div>
                            <div class="board-panel__title">Son sikayetler</div>
                        </div>
                        <a href="{{ route('admin.moderasyon.sikayetler') }}" class="board-panel__link">tumunu gor</a>
                    </div>

                    @if ($sonSikayetler->isEmpty())
                        <div class="board-empty">
                            <div class="board-empty__value">0</div>
                            <div class="board-empty__label">yeni sikayet yok</div>
                        </div>
                    @else
                        <div class="board-list">
                            @foreach ($sonSikayetler as $sikayet)
                                @php
                                    $sikayetEden =
                                        $sikayet->sikayetEden?->kullanici_adi ??
                                        ($sikayet->sikayetEden?->ad ?? 'Bilinmeyen');
                                    $sikayetEdilen =
                                        $sikayet->hedefUser?->kullanici_adi ??
                                        ($sikayet->hedefUser?->ad ?? 'Bilinmeyen');
                                    $sikayetOzet = $sikayet->aciklama
                                        ? \Illuminate\Support\Str::limit($sikayet->aciklama, 56)
                                        : ucfirst($sikayet->kategori);
                                    $avatar = mb_strtoupper(
                                        mb_substr($sikayetEden, 0, 1) . mb_substr($sikayetEdilen, 0, 1),
                                    );
                                    $durumSinifi = match ($sikayet->durum) {
                                        'beklemede', 'bekliyor' => 'board-status board-status--warn',
                                        'incelendi', 'inceleniyor' => 'board-status board-status--info',
                                        'reddedildi' => 'board-status board-status--danger',
                                        default => 'board-status board-status--success',
                                    };
                                @endphp

                                <a href="{{ route('admin.moderasyon.sikayetler.goster', $sikayet) }}"
                                    class="board-list__item">
                                    <div class="board-list__main">
                                        <div class="board-list__avatar">{{ $avatar }}</div>
                                        <div>
                                            <div class="board-list__title">{{ $sikayetEden }} &rarr;
                                                {{ $sikayetEdilen }}</div>
                                            <div class="board-list__sub">{{ $sikayetOzet }}</div>
                                        </div>
                                    </div>

                                    <span class="{{ $durumSinifi }}">{{ $sikayet->durum }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </article>
            </section>
        </div>
    </div>
@endsection
