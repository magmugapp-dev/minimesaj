@extends('admin.layout.ana')

@section('baslik', 'AI Studio JSON Import')

@section('icerik')
    <div class="studio studio--ai space-y-6" x-data="{ sayi: 0 }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('admin.ai.index') }}" class="studio-button studio-button--ghost">AI listesi</a>
            <h1 class="text-2xl font-semibold text-slate-950">JSON ile toplu AI ekle</h1>
        </div>

        @if (session('hata'))
            <div class="studio-alert">
                <div class="studio-alert__row">
                    <div class="studio-alert__icon">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86 1.82 18a2.25 2.25 0 0 0 1.93 3.37h16.5a2.25 2.25 0 0 0 1.93-3.37L13.71 3.86a2.25 2.25 0 0 0-3.42 0Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="studio-alert__title">Import hatasi</div>
                        <div class="studio-alert__copy">{{ session('hata') }}</div>
                    </div>
                </div>
            </div>
        @endif

        @if (session('hatalar'))
            <div class="studio-alert">
                <div class="studio-alert__row">
                    <div class="studio-alert__icon">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86 1.82 18a2.25 2.25 0 0 0 1.93 3.37h16.5a2.25 2.25 0 0 0 1.93-3.37L13.71 3.86a2.25 2.25 0 0 0-3.42 0Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="studio-alert__title">Bazi kayitlar olusmadi</div>
                        <ul class="studio-alert__list">
                            @foreach (session('hatalar') as $hata)
                                <li>{{ $hata }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.ai.json-kaydet') }}" class="studio-card">
            @csrf

            <div class="studio-card__header">
                <div>
                    <h2 class="studio-title">Import duzenleyicisi</h2>
                </div>
                <div class="ai-studio-inline-metric">
                    <span class="ai-studio-inline-metric__dot"></span>
                    <span x-text="sayi"></span> kayit algilandi
                </div>
            </div>

            <label>
                <span class="studio-label">JSON Verisi</span>
                <textarea id="jsonAlani" name="json_veri" rows="20"
                    placeholder="JSON formatinda AI kullanici verilerini buraya yapistirin..."
                    @input="try { const v = JSON.parse($el.value); sayi = Array.isArray(v) ? v.length : (typeof v === 'object' && v !== null ? 1 : 0); } catch { sayi = 0; }"
                    class="studio-textarea font-mono text-xs leading-relaxed">{{ old('json_veri', $sablon ?? '') }}</textarea>
                @error('json_veri')
                    <span class="studio-hint text-red-600">{{ $message }}</span>
                @enderror
            </label>

            <div class="studio-actions mt-6">
                <div class="studio-actions__buttons">
                    <a href="{{ route('admin.ai.ekle') }}" class="studio-button studio-button--ghost">Tekli Olusturma</a>
                    <button type="submit" class="studio-button studio-button--primary">Toplu Olustur</button>
                </div>
            </div>
        </form>
    </div>
@endsection
