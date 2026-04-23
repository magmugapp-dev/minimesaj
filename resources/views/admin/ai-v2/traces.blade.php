@extends('admin.layout.ana')

@section('baslik', 'AI Studio Trace')

@section('icerik')
    <div class="studio studio--ai space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-semibold text-slate-950">AI Trace</h1>
            <div class="text-sm font-semibold text-slate-500">{{ $traces->total() }} kayit</div>
        </div>

        @include('admin.ai-v2.partials.navigation')

        <form method="GET" class="studio-card">
            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Trace Aramasi</h2>
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

        @if ($traces->isEmpty())
            <div class="ai-studio-empty">Secili filtrelerde trace kaydi bulunamadi.</div>
        @else
            <div class="ai-studio-feed">
                @foreach ($traces as $trace)
                    @php
                        $tracePill = match ($trace->durum) {
                            'completed' => 'studio-pill--success',
                            'failed' => 'studio-pill--danger',
                            'processing' => 'studio-pill--warning',
                            default => 'studio-pill--neutral',
                        };
                    @endphp
                    <article class="studio-card">
                        <div class="studio-card__header">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="studio-kicker">{{ $trace->kanal }} / {{ $trace->turn_type }}</div>
                                    <h2 class="studio-title text-[1.5rem]">{{ $trace->aiUser?->ad }} {{ $trace->aiUser?->soyad }}</h2>
                                    <div class="mt-2 text-sm font-semibold text-slate-500">{{ optional($trace->created_at)->format('d.m.Y H:i') }}</div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="studio-pill {{ $tracePill }}">{{ $trace->durum }}</span>
                                    <span class="studio-pill studio-pill--neutral">{{ $trace->model_adi ?: 'model yok' }}</span>
                                    <span class="studio-pill studio-pill--info">{{ $trace->giris_token_sayisi ?: 0 }} / {{ $trace->cikis_token_sayisi ?: 0 }} token</span>
                                </div>
                            </div>
                        </div>

                        <div class="ai-studio-code-grid">
                            <div class="studio-surface">
                                <div class="studio-surface__title">Yorumlama</div>
                                <pre class="studio-code">{{ json_encode($trace->yorumlama, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div class="studio-surface">
                                <div class="studio-surface__title">Cevap Plani</div>
                                <pre class="studio-code">{{ json_encode($trace->cevap_plani, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div class="studio-surface">
                                <div class="studio-surface__title">Hafiza / Celiski</div>
                                <pre class="studio-code">{{ json_encode([
                                    'extraction' => data_get($trace->metadata, 'memory_extraction'),
                                    'stored_memory_ids' => data_get($trace->metadata, 'stored_memory_ids'),
                                    'contradictions' => data_get($trace->metadata, 'contradictions'),
                                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div class="studio-surface">
                                <div class="studio-surface__title">Evaluator</div>
                                <pre class="studio-code">{{ json_encode($trace->degerlendirme, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>

                        <div class="mt-6 studio-surface">
                            <div class="studio-surface__title">Final Cevap</div>
                            <div class="studio-copy-block">{{ $trace->cevap_metni ?: '-' }}</div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="studio-card">
                {{ $traces->links() }}
            </div>
        @endif
    </div>
@endsection


