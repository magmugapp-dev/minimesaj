@extends('admin.layout.ana')

@section('baslik', 'AI Persona Detayi')

@section('icerik')
    @php
        $stateCount = $states->count();
        $memoryCount = $memories->count();
        $traceCount = $traces->count();
        $selectedModel = data_get($persona->metadata, 'model_adi', array_key_first($modelOptions));
    @endphp

    <div class="studio studio--ai space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('admin.ai.index') }}" class="studio-button studio-button--ghost">AI Studio</a>
                <h1 class="text-2xl font-semibold text-slate-950">{{ $kullanici->ad }} {{ $kullanici->soyad }}</h1>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.ai.duzenle', $kullanici) }}" class="studio-button studio-button--primary">Duzenle</a>
                <a href="{{ route('admin.ai.traces', ['ai_user_id' => $kullanici->id]) }}" class="studio-button studio-button--ghost">Trace</a>
            </div>
        </div>

        @if (session('basari'))
            <div class="studio-notice studio-notice--success">{{ session('basari') }}</div>
        @endif

        @include('admin.ai-v2.partials.navigation')

        <section class="studio-stat-grid studio-stat-grid--4">
            <article class="studio-stat">
                <div class="studio-stat__label">State Kaydi</div>
                <div class="studio-stat__value">{{ $stateCount }}</div>
            </article>
            <article class="studio-stat">
                <div class="studio-stat__label">Hafiza</div>
                <div class="studio-stat__value">{{ $memoryCount }}</div>
            </article>
            <article class="studio-stat">
                <div class="studio-stat__label">Trace</div>
                <div class="studio-stat__value">{{ $traceCount }}</div>
            </article>
            <article class="studio-stat">
                <div class="studio-stat__label">Model</div>
                <div class="studio-stat__value text-lg">{{ $modelOptions[$selectedModel] ?? $selectedModel }}</div>
            </article>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.02fr,0.98fr]">
            <section class="studio-card">
                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Kimlik ve Persona Ozeti</h2>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="studio-surface">
                        <div class="studio-surface__title">Kimlik</div>
                        <div class="studio-data-value mt-2">{{ '@' . $kullanici->kullanici_adi }}</div>
                        <div class="mt-2 text-sm text-slate-600">
                            {{ $dropdowns['account_statuses'][$kullanici->hesap_durumu] ?? $kullanici->hesap_durumu }}
                            / {{ $dropdowns['genders'][$kullanici->cinsiyet] ?? $kullanici->cinsiyet }}
                        </div>
                        <div class="mt-2 text-sm text-slate-600">
                            {{ $kullanici->dogum_yili ?: '-' }} / {{ $kullanici->ulke ?: '-' }}
                        </div>
                    </div>
                    <div class="studio-surface">
                        <div class="studio-surface__title">Dil ve Lokasyon</div>
                        <div class="studio-data-value mt-2">{{ $persona->ana_dil_adi ?: '-' }}</div>
                        <div class="mt-2 text-sm text-slate-600">
                            {{ $persona->persona_ulke ?: '-' }} / {{ $persona->persona_bolge ?: '-' }} / {{ $persona->persona_sehir ?: '-' }}
                        </div>
                        @if (!empty($persona->ikinci_diller))
                            <div class="mt-2 text-sm text-slate-600">{{ implode(', ', $persona->ikinci_diller) }}</div>
                        @endif
                    </div>
                    <div class="studio-surface md:col-span-2">
                        <div class="studio-surface__title">Persona Ozeti</div>
                        <div class="studio-copy-block">{{ $persona->persona_ozeti ?: 'Bu persona icin yazili ozet bulunmuyor.' }}</div>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    @foreach ([
                        'Yasam Tarzi' => $persona->yasam_tarzi,
                        'Meslek' => $persona->meslek,
                        'Sektor' => $persona->sektor,
                        'Egitim' => $persona->egitim,
                        'Yas Araligi' => $persona->yas_araligi,
                        'Iliski Gecmisi' => $persona->iliski_gecmisi_tonu,
                    ] as $label => $value)
                        <div class="studio-surface">
                            <div class="studio-surface__title">{{ $label }}</div>
                            <div class="studio-data-value mt-2">{{ $value ?: '-' }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="studio-card">
                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Guardrail ve Zamanlama</h2>
                    </div>
                </div>

                <div class="ai-studio-stack">
                    <div class="studio-surface">
                        <div class="studio-surface__title">Ilk Mesaj Davranisi</div>
                        <div class="studio-data-value mt-2">{{ $persona->ilk_mesaj_atar_mi ? 'Ilk mesaji atar' : 'Ilk mesaji bekler' }}</div>
                        @if ($persona->ilk_mesaj_tonu)
                            <div class="studio-copy-block mt-3">{{ $persona->ilk_mesaj_tonu }}</div>
                        @endif
                    </div>
                    <div class="studio-surface">
                        <div class="studio-surface__title">Mesaj Boyu ve Uyku</div>
                        <div class="studio-data-value mt-2">{{ $persona->mesaj_uzunlugu_min }} - {{ $persona->mesaj_uzunlugu_max }} karakter</div>
                        <div class="mt-2 text-sm text-slate-600">{{ $persona->minimum_cevap_suresi_saniye }} - {{ $persona->maksimum_cevap_suresi_saniye }} saniye</div>
                        <div class="mt-2 text-sm text-slate-600">{{ $persona->saat_dilimi ?: config('app.timezone') }}</div>
                        <div class="mt-2 text-sm text-slate-600">{{ $persona->uyku_baslangic ?: '-' }} - {{ $persona->uyku_bitis ?: '-' }}</div>
                    </div>
                    <div class="studio-surface">
                        <div class="studio-surface__title">Yasakli Konular</div>
                        <div class="studio-copy-block">{{ $blockedTopicsText ?: 'Persona seviyesinde ayri yasakli konu tanimlanmadi.' }}</div>
                    </div>
                    <div class="studio-surface">
                        <div class="studio-surface__title">Zorunlu Kurallar</div>
                        <div class="studio-copy-block">{{ $requiredRulesText ?: 'Persona seviyesinde ayri zorunlu kural tanimlanmadi.' }}</div>
                    </div>
                </div>
            </section>
        </div>

        <section class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Davranis Matrisi</h2>
                </div>
            </div>

            <div class="space-y-6">
                @foreach ($behaviorSliderGroups as $group => $sliders)
                    <div class="space-y-4">
                        <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $group }}</div>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($sliders as $field => $meta)
                                @php $value = (int) ($persona->{$field} ?? ($meta['default'] ?? 5)); @endphp
                                <div class="studio-progress">
                                    <div class="studio-progress__top">
                                        <div class="studio-progress__label">{{ $meta['label'] }}</div>
                                        <div class="studio-progress__value">{{ $value }}/10</div>
                                    </div>
                                    <div class="studio-progress__track">
                                        <div class="studio-progress__fill" style="width: {{ max(0, min(100, $value * 10)) }}%"></div>
                                    </div>
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

        <div class="grid gap-6 xl:grid-cols-[0.92fr,1.08fr]">
            <section class="studio-card">
                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Son Sohbet Durumlari</h2>
                    </div>
                </div>

                @if ($states->isEmpty())
                    <div class="ai-studio-empty">Bu persona icin henuz kaydedilmis state yok.</div>
                @else
                    <div class="ai-studio-feed">
                        @foreach ($states as $state)
                            @php
                                $statePill = match ($state->ai_durumu) {
                                    'typing' => 'studio-pill--info',
                                    'queued' => 'studio-pill--warning',
                                    'blocked' => 'studio-pill--danger',
                                    default => 'studio-pill--neutral',
                                };
                            @endphp
                            <article class="ai-studio-list-card">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $state->kanal }} / {{ $state->hedef_tipi }}</div>
                                        <div class="mt-2 text-base font-semibold text-slate-950">Hedef #{{ $state->hedef_id }}</div>
                                    </div>
                                    <span class="studio-pill {{ $statePill }}">{{ $state->ai_durumu }}</span>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div class="studio-surface">
                                        <div class="studio-surface__title">Ruh Hali</div>
                                        <div class="studio-data-value mt-2">{{ $state->ruh_hali }}</div>
                                    </div>
                                    <div class="studio-surface">
                                        <div class="studio-surface__title">Son Duygu</div>
                                        <div class="studio-data-value mt-2">{{ $state->son_kullanici_duygusu ?: '-' }}</div>
                                    </div>
                                </div>
                                <div class="mt-4 text-sm leading-7 text-slate-600">
                                    Samimiyet {{ $state->samimiyet_puani }}, ilgi {{ $state->ilgi_puani }}, guven {{ $state->guven_puani }}.
                                    {{ $state->son_konu ? ' Son konu: ' . $state->son_konu . '.' : '' }}
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="studio-card">
                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Hafiza ve Son Uretimler</h2>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <div class="mb-3 text-sm font-semibold text-slate-900">Son Hafiza Kayitlari</div>
                        @if ($memories->isEmpty())
                            <div class="ai-studio-empty">Henuz memory olusmadi.</div>
                        @else
                            <div class="ai-studio-feed">
                                @foreach ($memories->take(8) as $memory)
                                    <article class="ai-studio-list-card">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="studio-pill studio-pill--info">{{ $memory->hafiza_tipi }}</span>
                                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Onem {{ $memory->onem_puani }}</span>
                                        </div>
                                        <div class="mt-3 text-sm leading-7 text-slate-700">{{ $memory->icerik }}</div>
                                        @if ($memory->anahtar)
                                            <div class="mt-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $memory->anahtar }}</div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="mb-3 text-sm font-semibold text-slate-900">Son Trace Kayitlari</div>
                        @if ($traces->isEmpty())
                            <div class="ai-studio-empty">Bu persona icin henuz trace yok.</div>
                        @else
                            <div class="ai-studio-feed">
                                @foreach ($traces->take(8) as $trace)
                                    <article class="ai-studio-list-card">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $trace->kanal }} / {{ $trace->turn_type }}</div>
                                                <div class="mt-2 text-sm font-semibold text-slate-950">{{ $trace->model_adi ?: 'Model kaydi yok' }}</div>
                                            </div>
                                            <span class="studio-pill {{ $trace->durum === 'completed' ? 'studio-pill--success' : ($trace->durum === 'failed' ? 'studio-pill--danger' : 'studio-pill--neutral') }}">{{ $trace->durum }}</span>
                                        </div>
                                        <div class="mt-3 text-sm leading-7 text-slate-700">{{ \Illuminate\Support\Str::limit($trace->cevap_metni ?: 'Cevap metni kaydi yok.', 180) }}</div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
