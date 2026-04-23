@extends('admin.layout.ana')

@section('baslik', 'AI Studio Memory')

@section('icerik')
    <div class="studio studio--ai space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-semibold text-slate-950">AI Hafiza</h1>
            <div class="text-sm font-semibold text-slate-500">{{ $memories->total() }} kayit</div>
        </div>

        @include('admin.ai-v2.partials.navigation')

        <form method="GET" class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Hafiza Aramasi</h2>
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

        @if ($memories->isEmpty())
            <div class="ai-studio-empty">Secili filtrelerde hafiza kaydi bulunamadi.</div>
        @else
            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                @foreach ($memories as $memory)
                    @php
                        $typePill = match ($memory->hafiza_tipi) {
                            'boundary' => 'studio-pill--danger',
                            'emotion' => 'studio-pill--warning',
                            'preference' => 'studio-pill--info',
                            'relationship' => 'studio-pill--success',
                            default => 'studio-pill--neutral',
                        };
                    @endphp
                    <article class="ai-studio-list-card">
                        <div class="flex items-start gap-4">
                            <div class="ai-studio-avatar">{{ mb_substr((string) ($memory->aiUser?->ad ?: 'A'), 0, 1) }}{{ mb_substr((string) ($memory->aiUser?->soyad ?: 'I'), 0, 1) }}</div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold text-slate-950">{{ $memory->aiUser?->ad }} {{ $memory->aiUser?->soyad }}</h3>
                                <div class="mt-1 text-sm text-slate-500">{{ $memory->kanal ?: 'genel' }} / {{ $memory->anahtar ?: 'anahtar yok' }}</div>
                            </div>
                        </div>

                        <div class="studio-pill-list mt-4">
                            <span class="studio-pill {{ $typePill }}">{{ $memory->hafiza_tipi }}</span>
                            <span class="studio-pill studio-pill--neutral">Onem {{ $memory->onem_puani }}</span>
                            @if ($memory->gecerlilik_tipi)
                                <span class="studio-pill studio-pill--info">{{ $memory->gecerlilik_tipi }}</span>
                            @endif
                            @if ($memory->guven_skoru !== null)
                                <span class="studio-pill studio-pill--success">Guven {{ number_format($memory->guven_skoru * 100, 0) }}%</span>
                            @endif
                        </div>

                        <div class="studio-copy-block mt-4">{{ $memory->icerik }}</div>
                        @if ($memory->deger || $memory->normalize_deger)
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="studio-surface">
                                    <div class="studio-surface__title">Deger</div>
                                    <div class="studio-data-value mt-2">{{ $memory->deger ?: '-' }}</div>
                                </div>
                                <div class="studio-surface">
                                    <div class="studio-surface__title">Normalize</div>
                                    <div class="studio-data-value mt-2">{{ $memory->normalize_deger ?: '-' }}</div>
                                </div>
                            </div>
                        @endif
                        @if (data_get($memory->metadata, 'previous_values'))
                            <div class="mt-4 studio-surface">
                                <div class="studio-surface__title">Eski Degerler</div>
                                <pre class="studio-code">{{ json_encode(data_get($memory->metadata, 'previous_values'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        @endif

                        <div class="mt-4 studio-progress">
                            <div class="studio-progress__top">
                                <div class="studio-progress__label">Recall Onemi</div>
                                <div class="studio-progress__value">{{ $memory->onem_puani * 10 }}%</div>
                            </div>
                            <div class="studio-progress__track">
                                <div class="studio-progress__fill" style="width: {{ max(0, min(100, $memory->onem_puani * 10)) }}%"></div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="studio-card">
                {{ $memories->links() }}
            </div>
        @endif
    </div>
@endsection


