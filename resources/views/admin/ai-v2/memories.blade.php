@extends('admin.layout.ana')

@section('baslik', 'AI Studio Memory')

@section('icerik')
    <div class="ai-console space-y-6">
        <section class="ai-studio-topbar">
            <div>
                <div class="ai-console-kicker">Memory</div>
                <h1 class="ai-console-title">AI Hafiza</h1>
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

        @if ($memories->isEmpty())
            <div class="ai-console-empty">Kayit yok.</div>
        @else
            <div class="ai-studio-list">
                @foreach ($memories as $memory)
                    @php
                        $typeBadge = match ($memory->hafiza_tipi) {
                            'boundary' => 'ai-console-badge--danger',
                            'emotion' => 'ai-console-badge--warning',
                            'preference' => 'ai-console-badge--accent',
                            'relationship' => 'ai-console-badge--success',
                            default => 'ai-console-badge',
                        };
                    @endphp
                    <article class="ai-studio-list__item">
                        <div class="ai-studio-list__top">
                            <div>
                                <div class="ai-studio-list__title">{{ $memory->aiUser?->ad }} {{ $memory->aiUser?->soyad }}</div>
                                <div class="ai-studio-list__meta">{{ $memory->kanal ?: 'genel' }} / {{ $memory->anahtar ?: '-' }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="ai-console-badge {{ $typeBadge }}">{{ $memory->hafiza_tipi }}</span>
                                <span class="ai-console-badge">Onem {{ $memory->onem_puani }}</span>
                            </div>
                        </div>
                        <div class="ai-studio-list__meta mt-4">{{ $memory->icerik }}</div>
                        <div class="ai-studio-mini-grid ai-studio-mini-grid--2 mt-4">
                            <div class="ai-studio-list__meta">Deger: {{ $memory->deger ?: '-' }}</div>
                            <div class="ai-studio-list__meta">Normalize: {{ $memory->normalize_deger ?: '-' }}</div>
                            @if ($memory->gecerlilik_tipi)
                                <div class="ai-studio-list__meta">Gecerlilik: {{ $memory->gecerlilik_tipi }}</div>
                            @endif
                            @if ($memory->guven_skoru !== null)
                                <div class="ai-studio-list__meta">Guven: {{ number_format($memory->guven_skoru * 100, 0) }}%</div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="ai-console-panel">
                {{ $memories->links() }}
            </div>
        @endif
    </div>
@endsection
