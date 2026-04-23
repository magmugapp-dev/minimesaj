@extends('admin.layout.ana')

@section('baslik', 'AI Studio Runtime')

@section('icerik')
    <div class="studio studio--ai space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-semibold text-slate-950">Sohbet Durumlari</h1>
            <div class="text-sm font-semibold text-slate-500">{{ $states->total() }} kayit</div>
        </div>

        @include('admin.ai-v2.partials.navigation')

        <form method="GET" class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Runtime Aramasi</h2>
                </div>
            </div>

            <div class="ai-studio-filter-grid">
                <label>
                    <span class="studio-label">AI Kullanici</span>
                    <select name="ai_user_id" class="studio-select">
                        <option value="">Tum AI kullanicilar</option>
                        @foreach ($aiUsers as $aiUser)
                            <option value="{{ $aiUser->id }}" @selected(request('ai_user_id') == $aiUser->id)>{{ $aiUser->ad }} {{ $aiUser->soyad }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="studio-label">Kanal</span>
                    <select name="kanal" class="studio-select">
                        <option value="">Tum kanallar</option>
                        <option value="dating" @selected(request('kanal') === 'dating')>Dating</option>
                        <option value="instagram" @selected(request('kanal') === 'instagram')>Instagram</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button class="studio-button studio-button--primary w-full">Filtrele</button>
                </div>
            </div>
        </form>

        @if ($states->isEmpty())
            <div class="ai-studio-empty">Secili filtrelerde state kaydi bulunamadi.</div>
        @else
            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($states as $state)
                    @php
                        $durumPill = match ($state->ai_durumu) {
                            'typing' => 'studio-pill--info',
                            'queued' => 'studio-pill--warning',
                            'blocked' => 'studio-pill--danger',
                            default => 'studio-pill--neutral',
                        };
                    @endphp
                    <article class="ai-studio-list-card">
                        <div class="flex items-start gap-4">
                            <div class="ai-studio-avatar">{{ mb_substr((string) ($state->aiUser?->ad ?: 'A'), 0, 1) }}{{ mb_substr((string) ($state->aiUser?->soyad ?: 'I'), 0, 1) }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-semibold text-slate-950">{{ $state->aiUser?->ad }} {{ $state->aiUser?->soyad }}</h3>
                                    <span class="studio-pill {{ $durumPill }}">{{ $state->ai_durumu }}</span>
                                </div>
                                <div class="mt-1 text-sm text-slate-500">{{ $state->kanal }} / {{ $state->hedef_tipi }} #{{ $state->hedef_id }}</div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="studio-surface">
                                <div class="studio-surface__title">Ruh Hali</div>
                                <div class="studio-data-value mt-2">{{ $state->ruh_hali }}</div>
                            </div>
                            <div class="studio-surface">
                                <div class="studio-surface__title">Son Duygu</div>
                                <div class="studio-data-value mt-2">{{ $state->son_kullanici_duygusu ?: '-' }}</div>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            @foreach ([
                                'Samimiyet' => max(0, min(100, (int) (($state->samimiyet_puani + 100) / 2))),
                                'Ilgi' => max(0, min(100, (int) (($state->ilgi_puani + 100) / 2))),
                                'Guven' => max(0, min(100, (int) (($state->guven_puani + 100) / 2))),
                            ] as $label => $value)
                                <div class="studio-progress">
                                    <div class="studio-progress__top">
                                        <div class="studio-progress__label">{{ $label }}</div>
                                        <div class="studio-progress__value">{{ $value }}%</div>
                                    </div>
                                    <div class="studio-progress__track">
                                        <div class="studio-progress__fill" style="width: {{ $value }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 text-xs uppercase tracking-[0.18em] text-slate-400">{{ optional($state->durum_guncellendi_at)->format('d.m.Y H:i') }}</div>
                    </article>
                @endforeach
            </div>

            <div class="studio-card">
                {{ $states->links() }}
            </div>
        @endif
    </div>
@endsection


