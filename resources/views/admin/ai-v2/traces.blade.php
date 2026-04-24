@extends('admin.layout.ana')

@section('baslik', 'AI Studio Trace')

@section('icerik')
    <div class="ai-console space-y-6">
        <section class="ai-studio-topbar">
            <div>
                <div class="ai-console-kicker">Trace</div>
                <h1 class="ai-console-title">AI Trace</h1>
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

        @if ($traces->isEmpty())
            <div class="ai-console-empty">Kayit yok.</div>
        @else
            <div class="ai-studio-list">
                @foreach ($traces as $trace)
                    @php
                        $traceBadge = match ($trace->durum) {
                            'completed' => 'ai-console-badge--success',
                            'failed' => 'ai-console-badge--danger',
                            'processing' => 'ai-console-badge--warning',
                            default => 'ai-console-badge',
                        };
                    @endphp
                    <article class="ai-studio-list__item">
                        <div class="ai-studio-list__top">
                            <div>
                                <div class="ai-studio-list__title">{{ $trace->aiUser?->ad }} {{ $trace->aiUser?->soyad }}</div>
                                <div class="ai-studio-list__meta">{{ $trace->kanal }} / {{ $trace->turn_type }} / {{ optional($trace->created_at)->format('d.m.Y H:i') }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="ai-console-badge {{ $traceBadge }}">{{ $trace->durum }}</span>
                                <span class="ai-console-badge">{{ $trace->model_adi ?: '-' }}</span>
                                <span class="ai-console-badge">{{ $trace->giris_token_sayisi ?: 0 }} / {{ $trace->cikis_token_sayisi ?: 0 }}</span>
                            </div>
                        </div>
                        <div class="ai-studio-mini-grid ai-studio-mini-grid--2 mt-4">
                            <div>
                                <div class="ai-console-label">Yorumlama</div>
                                <pre class="ai-console-code mt-3">{{ json_encode($trace->yorumlama, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div>
                                <div class="ai-console-label">Cevap Plani</div>
                                <pre class="ai-console-code mt-3">{{ json_encode($trace->cevap_plani, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div>
                                <div class="ai-console-label">Hafiza / Celiski</div>
                                <pre class="ai-console-code mt-3">{{ json_encode([
                                    'extraction' => data_get($trace->metadata, 'memory_extraction'),
                                    'stored_memory_ids' => data_get($trace->metadata, 'stored_memory_ids'),
                                    'contradictions' => data_get($trace->metadata, 'contradictions'),
                                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            <div>
                                <div class="ai-console-label">Evaluator</div>
                                <pre class="ai-console-code mt-3">{{ json_encode($trace->degerlendirme, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="ai-console-label">Final Cevap</div>
                            <div class="ai-console-copy mt-3">{{ $trace->cevap_metni ?: '-' }}</div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="ai-console-panel">
                {{ $traces->links() }}
            </div>
        @endif
    </div>
@endsection
