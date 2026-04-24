@extends('admin.layout.ana')

@section('baslik', 'AI Persona Detayi')

@section('icerik')
    @php
        $stateCount = $states->count();
        $memoryCount = $memories->count();
        $traceCount = $traces->count();
        $selectedModel = data_get($persona->metadata, 'model_adi', array_key_first($modelOptions));
    @endphp

    <div class="ai-console space-y-6">
        <section class="ai-studio-topbar">
            <div>
                <div class="ai-console-kicker">AI Persona</div>
                <h1 class="ai-console-title">{{ $kullanici->ad }} {{ $kullanici->soyad }}</h1>
                <div class="ai-console-subtitle">{{ '@' . $kullanici->kullanici_adi }}</div>
            </div>
            <div class="ai-studio-topbar__actions">
                <a href="{{ route('admin.ai.index') }}" class="ai-console-button ai-console-button--ghost">Studio</a>
                <a href="{{ route('admin.ai.duzenle', $kullanici) }}" class="ai-console-button ai-console-button--primary">Duzenle</a>
                <a href="{{ route('admin.ai.traces', ['ai_user_id' => $kullanici->id]) }}" class="ai-console-button ai-console-button--ghost">Trace</a>
            </div>
        </section>

        @if (session('basari'))
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-300">{{ session('basari') }}</div>
        @endif

        @include('admin.ai-v2.partials.navigation')

        <section class="ai-studio-summary">
            <article class="ai-studio-summary__item"><div class="ai-studio-summary__label">State</div><div class="ai-studio-summary__value">{{ $stateCount }}</div></article>
            <article class="ai-studio-summary__item"><div class="ai-studio-summary__label">Hafiza</div><div class="ai-studio-summary__value">{{ $memoryCount }}</div></article>
            <article class="ai-studio-summary__item"><div class="ai-studio-summary__label">Trace</div><div class="ai-studio-summary__value">{{ $traceCount }}</div></article>
            <article class="ai-studio-summary__item"><div class="ai-studio-summary__label">Model</div><div class="ai-studio-summary__value !text-xl">{{ $modelOptions[$selectedModel] ?? $selectedModel }}</div></article>
        </section>

        <div class="ai-studio-grid--split">
            <section class="ai-console-panel space-y-5">
                <div class="ai-console-panel__header"><h2 class="ai-console-panel__title">Kimlik</h2></div>
                <div class="ai-studio-mini-grid ai-studio-mini-grid--2">
                    <div class="ai-studio-list__item">
                        <div class="ai-studio-list__title">Genel</div>
                        <div class="ai-studio-list__meta mt-2">{{ $dropdowns['account_statuses'][$kullanici->hesap_durumu] ?? $kullanici->hesap_durumu }} / {{ $dropdowns['genders'][$kullanici->cinsiyet] ?? $kullanici->cinsiyet }}</div>
                        <div class="ai-studio-list__meta">{{ $kullanici->dogum_yili ?: '-' }} / {{ $kullanici->ulke ?: '-' }}</div>
                    </div>
                    <div class="ai-studio-list__item">
                        <div class="ai-studio-list__title">Dil ve Lokasyon</div>
                        <div class="ai-studio-list__meta mt-2">{{ $persona->ana_dil_adi ?: '-' }}</div>
                        <div class="ai-studio-list__meta">{{ $persona->persona_ulke ?: '-' }} / {{ $persona->persona_bolge ?: '-' }} / {{ $persona->persona_sehir ?: '-' }}</div>
                        @if (!empty($persona->ikinci_diller))
                            <div class="ai-studio-list__meta">{{ implode(', ', $persona->ikinci_diller) }}</div>
                        @endif
                    </div>
                </div>
                <div class="ai-studio-list__item">
                    <div class="ai-studio-list__title">Persona Ozeti</div>
                    <div class="ai-studio-list__meta mt-2">{{ $persona->persona_ozeti ?: '-' }}</div>
                </div>
                <div class="ai-studio-mini-grid ai-studio-mini-grid--2">
                    @foreach ([
                        'Yasam Tarzi' => $persona->yasam_tarzi,
                        'Meslek' => $persona->meslek,
                        'Sektor' => $persona->sektor,
                        'Egitim' => $persona->egitim,
                        'Yas Araligi' => $persona->yas_araligi,
                        'Iliski Gecmisi' => $persona->iliski_gecmisi_tonu,
                    ] as $label => $value)
                        <div class="ai-studio-list__item">
                            <div class="ai-studio-list__title">{{ $label }}</div>
                            <div class="ai-studio-list__meta mt-2">{{ $value ?: '-' }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ai-console-panel space-y-5">
                <div class="ai-console-panel__header"><h2 class="ai-console-panel__title">Kurallar ve Davranis</h2></div>
                <div class="ai-studio-mini-grid ai-studio-mini-grid--2">
                    <div class="ai-studio-list__item">
                        <div class="ai-studio-list__title">Ilk Mesaj</div>
                        <div class="ai-studio-list__meta mt-2">{{ $persona->ilk_mesaj_atar_mi ? 'Atar' : 'Bekler' }}</div>
                        <div class="ai-studio-list__meta">{{ $persona->ilk_mesaj_tonu ?: '-' }}</div>
                    </div>
                    <div class="ai-studio-list__item">
                        <div class="ai-studio-list__title">Zamanlama</div>
                        <div class="ai-studio-list__meta mt-2">{{ $persona->mesaj_uzunlugu_min }} - {{ $persona->mesaj_uzunlugu_max }} karakter</div>
                        <div class="ai-studio-list__meta">{{ $persona->minimum_cevap_suresi_saniye }} - {{ $persona->maksimum_cevap_suresi_saniye }} saniye</div>
                        <div class="ai-studio-list__meta">{{ $persona->uyku_baslangic ?: '-' }} / {{ $persona->uyku_bitis ?: '-' }}</div>
                    </div>
                    <div class="ai-studio-list__item">
                        <div class="ai-studio-list__title">Yasakli Konular</div>
                        <div class="ai-studio-list__meta mt-2">{{ $blockedTopicsText ?: '-' }}</div>
                    </div>
                    <div class="ai-studio-list__item">
                        <div class="ai-studio-list__title">Zorunlu Kurallar</div>
                        <div class="ai-studio-list__meta mt-2">{{ $requiredRulesText ?: '-' }}</div>
                    </div>
                </div>
            </section>
        </div>

        <section class="ai-console-panel space-y-4">
            <div class="ai-console-panel__header"><h2 class="ai-console-panel__title">Davranis Matrisi</h2></div>
            <div class="ai-studio-mini-grid ai-studio-mini-grid--2">
                @foreach ($behaviorSliderGroups as $group => $sliders)
                    <div class="ai-studio-list__item">
                        <div class="ai-studio-list__title">{{ $group }}</div>
                        <div class="space-y-3 mt-4">
                            @foreach ($sliders as $field => $meta)
                                @php $value = (int) ($persona->{$field} ?? ($meta['default'] ?? 5)); @endphp
                                <div class="ai-console-progress">
                                    <div class="ai-console-progress__top">
                                        <div class="ai-console-progress__label">{{ $meta['label'] }}</div>
                                        <div class="ai-console-progress__value">{{ $value }}/10</div>
                                    </div>
                                    <div class="ai-console-progress__track"><div class="ai-console-progress__fill" style="width: {{ max(0, min(100, $value * 10)) }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="ai-studio-grid--split">
            <section class="ai-console-panel space-y-4">
                <div class="ai-console-panel__header"><h2 class="ai-console-panel__title">Son Durumlar</h2></div>
                @if ($states->isEmpty())
                    <div class="ai-console-empty">Kayit yok.</div>
                @else
                    <div class="ai-studio-list">
                        @foreach ($states as $state)
                            @php
                                $stateBadge = match ($state->ai_durumu) {
                                    'typing' => 'ai-console-badge--accent',
                                    'queued' => 'ai-console-badge--warning',
                                    'blocked' => 'ai-console-badge--danger',
                                    default => 'ai-console-badge',
                                };
                            @endphp
                            <article class="ai-studio-list__item">
                                <div class="ai-studio-list__top">
                                    <div>
                                        <div class="ai-studio-list__title">{{ $state->kanal }} / {{ $state->hedef_tipi }} #{{ $state->hedef_id }}</div>
                                        <div class="ai-studio-list__meta">{{ optional($state->durum_guncellendi_at)->format('d.m.Y H:i') }}</div>
                                    </div>
                                    <span class="ai-console-badge {{ $stateBadge }}">{{ $state->ai_durumu }}</span>
                                </div>
                                <div class="ai-studio-list__meta mt-3">Ruh hali {{ $state->ruh_hali }} / Son duygu {{ $state->son_kullanici_duygusu ?: '-' }}</div>
                                <div class="ai-studio-list__meta">Samimiyet {{ $state->samimiyet_puani }}, ilgi {{ $state->ilgi_puani }}, guven {{ $state->guven_puani }}</div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="ai-console-panel space-y-4">
                <div class="ai-console-panel__header"><h2 class="ai-console-panel__title">Hafiza ve Son Trace</h2></div>
                <div class="ai-studio-list">
                    @foreach ($memories->take(4) as $memory)
                        <article class="ai-studio-list__item">
                            <div class="ai-studio-list__top">
                                <div class="ai-studio-list__title">{{ $memory->hafiza_tipi }}</div>
                                <div class="ai-studio-list__meta">Onem {{ $memory->onem_puani }}</div>
                            </div>
                            <div class="ai-studio-list__meta mt-3">{{ $memory->icerik }}</div>
                        </article>
                    @endforeach
                    @foreach ($traces->take(4) as $trace)
                        <article class="ai-studio-list__item">
                            <div class="ai-studio-list__top">
                                <div class="ai-studio-list__title">{{ $trace->model_adi ?: '-' }}</div>
                                <span class="ai-console-badge {{ $trace->durum === 'completed' ? 'ai-console-badge--success' : ($trace->durum === 'failed' ? 'ai-console-badge--danger' : 'ai-console-badge') }}">{{ $trace->durum }}</span>
                            </div>
                            <div class="ai-studio-list__meta mt-3">{{ \Illuminate\Support\Str::limit($trace->cevap_metni ?: '-', 150) }}</div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
