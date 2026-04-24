@extends('admin.layout.ana')

@section('baslik', 'AI Studio Runtime')

@section('icerik')
    <div class="ai-console space-y-6">
        <section class="ai-studio-topbar">
            <div>
                <div class="ai-console-kicker">Runtime</div>
                <h1 class="ai-console-title">Sohbet Durumlari</h1>
            </div>
        </section>

        @include('admin.ai-v2.partials.navigation')

        <form method="GET" class="ai-console-panel">
            <div class="ai-studio-form-grid ai-studio-form-grid--3">
                <label>
                    <span class="ai-console-label">AI Kullanici</span>
                    <select name="ai_user_id" class="ai-console-select">
                        <option value="">Tum AI kullanicilar</option>
                        @foreach ($aiUsers as $aiUser)
                            <option value="{{ $aiUser->id }}" @selected(request('ai_user_id') == $aiUser->id)>{{ $aiUser->ad }} {{ $aiUser->soyad }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="ai-console-label">Kanal</span>
                    <select name="kanal" class="ai-console-select">
                        <option value="">Tum kanallar</option>
                        <option value="dating" @selected(request('kanal') === 'dating')>Dating</option>
                        <option value="instagram" @selected(request('kanal') === 'instagram')>Instagram</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button class="ai-console-button ai-console-button--primary w-full">Filtrele</button>
                </div>
            </div>
        </form>

        @if ($states->isEmpty())
            <div class="ai-console-empty">Kayit yok.</div>
        @else
            <div class="ai-studio-list">
                @foreach ($states as $state)
                    @php
                        $durumBadge = match ($state->ai_durumu) {
                            'typing' => 'ai-console-badge--accent',
                            'queued' => 'ai-console-badge--warning',
                            'blocked' => 'ai-console-badge--danger',
                            default => 'ai-console-badge',
                        };
                    @endphp
                    <article class="ai-studio-list__item">
                        <div class="ai-studio-list__top">
                            <div>
                                <div class="ai-studio-list__title">{{ $state->aiUser?->ad }} {{ $state->aiUser?->soyad }}</div>
                                <div class="ai-studio-list__meta">{{ $state->kanal }} / {{ $state->hedef_tipi }} #{{ $state->hedef_id }}</div>
                            </div>
                            <span class="ai-console-badge {{ $durumBadge }}">{{ $state->ai_durumu }}</span>
                        </div>
                        <div class="ai-studio-mini-grid ai-studio-mini-grid--2 mt-4">
                            <div class="ai-studio-list__meta">Ruh hali: {{ $state->ruh_hali }}</div>
                            <div class="ai-studio-list__meta">Son duygu: {{ $state->son_kullanici_duygusu ?: '-' }}</div>
                            <div class="ai-studio-list__meta">Samimiyet: {{ $state->samimiyet_puani }}</div>
                            <div class="ai-studio-list__meta">Ilgi: {{ $state->ilgi_puani }}</div>
                            <div class="ai-studio-list__meta">Guven: {{ $state->guven_puani }}</div>
                            <div class="ai-studio-list__meta">{{ optional($state->durum_guncellendi_at)->format('d.m.Y H:i') }}</div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="ai-console-panel">
                {{ $states->links() }}
            </div>
        @endif
    </div>
@endsection
