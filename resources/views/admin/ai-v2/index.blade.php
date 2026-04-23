@extends('admin.layout.ana')

@section('baslik', 'AI Studio')

@section('icerik')
    <div class="studio studio--ai space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-semibold text-slate-950">AI Studio</h1>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.ai.ekle') }}" class="studio-button studio-button--primary">Yeni AI Kullanici</a>
                <a href="{{ route('admin.ai.traces') }}" class="studio-button studio-button--ghost">AI Trace</a>
            </div>
        </div>

        @if (session('basari'))
            <div class="studio-notice studio-notice--success">
                {{ session('basari') }}
            </div>
        @endif

        @include('admin.ai-v2.partials.navigation')

        <section class="studio-stat-grid studio-stat-grid--4">
            <article class="studio-stat">
                <div class="studio-stat__label">Persona Havuzu</div>
                <div class="studio-stat__value">{{ $istatistikler['persona_sayisi'] }}</div>
            </article>
            <article class="studio-stat">
                <div class="studio-stat__label">Aktif Persona</div>
                <div class="studio-stat__value">{{ $istatistikler['aktif_persona'] }}</div>
            </article>
            <article class="studio-stat">
                <div class="studio-stat__label">Live Runtime</div>
                <div class="studio-stat__value">{{ $istatistikler['aktif_state'] }}</div>
            </article>
            <article class="studio-stat">
                <div class="studio-stat__label">Today Turns</div>
                <div class="studio-stat__value">{{ $istatistikler['bugunku_turn'] }}</div>
            </article>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.12fr,1fr]">
            <form method="POST" action="{{ route('admin.ai.engine.update') }}" class="studio-card">
                @csrf

                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Motor Ayarlari</h2>
                    </div>
                    <div class="ai-studio-inline-metric">
                        <span class="ai-studio-inline-metric__dot"></span>
                        {{ $config->aktif_mi ? 'Motor aktif' : 'Motor pasif' }}
                    </div>
                </div>

                <div class="studio-toggle-grid studio-form-grid--2">
                    <label class="studio-toggle">
                        <div class="studio-toggle__row">
                            <div>
                                <div class="studio-toggle__title">Motor Aktif</div>
                            </div>
                            <input class="studio-check" type="checkbox" name="aktif_mi" value="1" @checked($config->aktif_mi)>
                        </div>
                    </label>
                    <div class="studio-surface">
                        <div class="studio-surface__title">Provider</div>
                        <div class="studio-data-value mt-3">Gemini</div>
                    </div>
                </div>

                <div class="studio-form-grid studio-form-grid--2 mt-6">
                    <label>
                        <span class="studio-label">Model</span>
                        <input class="studio-input" type="text" name="model_adi" value="{{ old('model_adi', $config->model_adi) }}" placeholder="gemini-2.5-flash">
                    </label>
                    <label>
                        <span class="studio-label">Temperature</span>
                        <input class="studio-input" type="number" step="0.01" min="0" max="2" name="temperature" value="{{ old('temperature', $config->temperature) }}">
                    </label>
                    <label>
                        <span class="studio-label">Top P</span>
                        <input class="studio-input" type="number" step="0.01" min="0" max="1" name="top_p" value="{{ old('top_p', $config->top_p) }}">
                    </label>
                    <label>
                        <span class="studio-label">Max Output Tokens</span>
                        <input class="studio-input" type="number" min="64" max="8192" name="max_output_tokens" value="{{ old('max_output_tokens', $config->max_output_tokens) }}">
                    </label>
                </div>

                <div class="mt-6">
                    <label>
                        <span class="studio-label">Global Sistem Komutu</span>
                        <textarea class="studio-textarea" name="sistem_komutu" rows="6" placeholder="Motorun tum personalar icin tasiyacagi temel tavir ve guardrail dili.">{{ old('sistem_komutu', $config->sistem_komutu) }}</textarea>
                    </label>
                </div>

                <div class="studio-form-grid studio-form-grid--2 mt-6">
                    <label>
                        <span class="studio-label">Global Yasakli Konular</span>
                        <textarea class="studio-textarea" name="blocked_topics" rows="8" placeholder="Her satir yeni bir yasakli konu.">{{ old('blocked_topics', $blockedTopicsText) }}</textarea>
                    </label>
                    <label>
                        <span class="studio-label">Global Zorunlu Kurallar</span>
                        <textarea class="studio-textarea" name="required_rules" rows="8" placeholder="Her satir yeni bir zorunlu kural.">{{ old('required_rules', $requiredRulesText) }}</textarea>
                    </label>
                </div>

                <div class="studio-actions mt-6">
                    <div class="studio-actions__buttons">
                        <button class="studio-button studio-button--primary">Motoru Guncelle</button>
                    </div>
                </div>
            </form>

            <section class="studio-card">
                <div class="studio-card__header">
                    <div>
                        <h2 class="studio-title">Aktif AI Karakterleri</h2>
                    </div>
                    <a href="{{ route('admin.ai.ekle') }}" class="studio-button studio-button--ghost">AI Kullanici Ekle</a>
                </div>

                @if ($personalar->isEmpty())
                    <div class="ai-studio-empty">Henuz AI persona kaydi yok. Ilk karakteri eklediginde burasi bir roster panosuna donecek.</div>
                @else
                    <div class="ai-studio-persona-grid max-h-[52rem] overflow-y-auto pr-1">
                        @foreach ($personalar as $personaUser)
                            @php $persona = $personaUser->aiPersonaProfile; @endphp
                            <article class="ai-studio-list-card">
                                <div class="flex items-start gap-4">
                                    <div class="ai-studio-avatar">
                                        {{ mb_substr((string) $personaUser->ad, 0, 1) }}{{ mb_substr((string) $personaUser->soyad, 0, 1) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-semibold text-slate-950">{{ $personaUser->ad }} {{ $personaUser->soyad }}</h3>
                                            <span class="studio-pill {{ $persona?->aktif_mi ? 'studio-pill--success' : 'studio-pill--neutral' }}">{{ $persona?->aktif_mi ? 'Aktif' : 'Pasif' }}</span>
                                        </div>
                                        <div class="mt-1 text-sm text-slate-500">{{ '@' . $personaUser->kullanici_adi }}</div>
                                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ \Illuminate\Support\Str::limit($persona?->persona_ozeti ?: ($personaUser->biyografi ?: 'Persona ozeti henuz tanimli degil.'), 120) }}</p>
                                    </div>
                                </div>

                                <div class="studio-pill-list mt-4">
                                    <span class="studio-pill studio-pill--info">{{ $persona?->dating_aktif_mi ? 'Dating acik' : 'Dating kapali' }}</span>
                                    <span class="studio-pill studio-pill--neutral">{{ $persona?->instagram_aktif_mi ? 'Instagram acik' : 'Instagram kapali' }}</span>
                                    <span class="studio-pill studio-pill--neutral">Ton {{ $persona?->konusma_tonu ?: 'dogal' }}</span>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div class="studio-surface">
                                        <div class="studio-surface__title">Flort / Emoji</div>
                                        <div class="studio-data-value mt-2">{{ $persona?->flort_seviyesi }}/10 - {{ $persona?->emoji_seviyesi }}/10</div>
                                    </div>
                                    <div class="studio-surface">
                                        <div class="studio-surface__title">Mesaj Boyu</div>
                                        <div class="studio-data-value mt-2">{{ $persona?->mesaj_uzunlugu_min }} - {{ $persona?->mesaj_uzunlugu_max }}</div>
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-3">
                                    <a href="{{ route('admin.ai.goster', $personaUser) }}" class="studio-button studio-button--primary">Detay</a>
                                    <a href="{{ route('admin.ai.duzenle', $personaUser) }}" class="studio-button studio-button--ghost">Duzenle</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <section class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Son AI Trace Kayitlari</h2>
                </div>
                <a href="{{ route('admin.ai.traces') }}" class="studio-button studio-button--ghost">Tum Trace Kayitlari</a>
            </div>

            @if ($sonTraceler->isEmpty())
                <div class="ai-studio-empty">Henuz trace olusmadi. Ilk kullanici-AI etkilesiminde evaluator ve cikti kayitlari burada akmaya baslar.</div>
            @else
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach ($sonTraceler as $trace)
                        @php
                            $tracePill = match ($trace->durum) {
                                'completed' => 'studio-pill--success',
                                'failed' => 'studio-pill--danger',
                                'processing' => 'studio-pill--warning',
                                default => 'studio-pill--neutral',
                            };
                        @endphp
                        <article class="ai-studio-list-card">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $trace->kanal }} / {{ $trace->turn_type }}</div>
                                    <h3 class="mt-2 text-base font-semibold text-slate-950">{{ $trace->aiUser?->ad }} {{ $trace->aiUser?->soyad }}</h3>
                                    <div class="mt-1 text-sm text-slate-500">{{ optional($trace->created_at)->format('d.m.Y H:i') }}</div>
                                </div>
                                <span class="studio-pill {{ $tracePill }}">{{ $trace->durum }}</span>
                            </div>

                            <div class="mt-4 space-y-3 text-sm text-slate-600">
                                <div class="flex items-center justify-between gap-3">
                                    <span>Model</span>
                                    <span class="font-semibold text-slate-900">{{ $trace->model_adi ?: '-' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span>Token</span>
                                    <span class="font-semibold text-slate-900">{{ $trace->giris_token_sayisi ?: 0 }} / {{ $trace->cikis_token_sayisi ?: 0 }}</span>
                                </div>
                            </div>

                            <div class="studio-copy-block mt-4">{{ $trace->cevap_metni ?: 'Henuz cevap metni kaydi yok.' }}</div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection


