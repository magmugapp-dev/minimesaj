@extends('admin.layout.ana')

@section('baslik', 'AI Studio')

@section('icerik')
    <div class="ai-console space-y-6">
        <section class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">AI Studio</div>
                <h1 class="mt-1 text-3xl font-bold text-gray-900">AI kullanicilarini yonet</h1>
                <p class="mt-2 text-sm text-gray-500">Motoru kontrol et, AI kullanicilarini bul ve son hareketleri tek ekrandan izle.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.ai.ekle') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Yeni AI ekle</a>
                <a href="{{ route('admin.ai.json-ekle') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">JSON import</a>
                <a href="{{ route('admin.ai.traces') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Tum kayitlar</a>
            </div>
        </section>

        @if (session('basari'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('basari') }}
            </div>
        @endif

        @include('admin.ai-v2.partials.navigation')

        <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Toplam AI</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">{{ $istatistikler['persona_sayisi'] }}</div>
                <div class="mt-1 text-sm text-gray-500">Sistemdeki toplam persona</div>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Aktif AI</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">{{ $istatistikler['aktif_persona'] }}</div>
                <div class="mt-1 text-sm text-gray-500">Mesajlasmaya hazir</div>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Canli akis</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">{{ $istatistikler['aktif_state'] }}</div>
                <div class="mt-1 text-sm text-gray-500">Typing veya queued</div>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm md:col-span-3 xl:col-span-1">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Bugunku turn</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">{{ $istatistikler['bugunku_turn'] }}</div>
                <div class="mt-1 text-sm text-gray-500">Bugun olusan toplam cevap</div>
            </article>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">1. Adim</div>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900">Motor ayarlari</h2>
                        <p class="mt-1 text-sm text-gray-500">Temel ayarlari burada degistir. Ileri ayarlar istege bagli olarak acilabilir.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full {{ $config->aktif_mi ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }} px-3 py-1 text-xs font-semibold">
                        {{ $config->aktif_mi ? 'Motor aktif' : 'Motor pasif' }}
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.ai.engine.update') }}" class="space-y-5 px-5 py-5">
                @csrf
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Motor aktif</span>
                        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <input class="h-5 w-5 accent-indigo-600" type="checkbox" name="aktif_mi" value="1" @checked($config->aktif_mi)>
                            <span class="text-sm text-gray-700">Cevap uretimi acik</span>
                        </div>
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Provider</span>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-800">Gemini</div>
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Model</span>
                        <select class="ai-console-select" name="model_adi">
                            @foreach ($modelOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('model_adi', $config->model_adi) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Temperature</span>
                        <input class="ai-console-input" type="number" step="0.01" min="0" max="2" name="temperature" value="{{ old('temperature', $config->temperature) }}">
                    </label>
                </div>

                <details class="rounded-lg border border-gray-200 bg-gray-50">
                    <summary class="cursor-pointer list-none px-4 py-3 text-sm font-medium text-gray-700">Ileri ayarlar</summary>
                    <div class="grid gap-4 border-t border-gray-200 px-4 py-4 md:grid-cols-2 xl:grid-cols-4">
                        <label class="block">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Top P</span>
                            <input class="ai-console-input" type="number" step="0.01" min="0" max="1" name="top_p" value="{{ old('top_p', $config->top_p) }}">
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Max output tokens</span>
                            <input class="ai-console-input" type="number" min="64" max="8192" name="max_output_tokens" value="{{ old('max_output_tokens', $config->max_output_tokens) }}">
                        </label>
                        <label class="block md:col-span-2 xl:col-span-4">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Global sistem komutu</span>
                            <textarea class="ai-console-textarea" name="sistem_komutu" rows="5">{{ old('sistem_komutu', $config->sistem_komutu) }}</textarea>
                        </label>
                        <label class="block md:col-span-2">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Yasakli konular</span>
                            <textarea class="ai-console-textarea" name="blocked_topics" rows="4">{{ old('blocked_topics', $blockedTopicsText) }}</textarea>
                        </label>
                        <label class="block md:col-span-2">
                            <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Zorunlu kurallar</span>
                            <textarea class="ai-console-textarea" name="required_rules" rows="4">{{ old('required_rules', $requiredRulesText) }}</textarea>
                        </label>
                    </div>
                </details>

                <div class="flex justify-end">
                    <button class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Ayarlari kaydet</button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">2. Adim</div>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900">AI kullanici listesi</h2>
                        <p class="mt-1 text-sm text-gray-500">Aradigin AI kullaniciyi bul, detayini ac veya duzenle.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <form method="GET" action="{{ route('admin.ai.index') }}" class="relative min-w-[18rem]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.473 9.766l3.63 3.631a.75.75 0 1 0 1.06-1.06l-3.63-3.63A5.5 5.5 0 0 0 9 3.5Zm-4 5.5a4 4 0 1 1 8 0a4 4 0 0 1-8 0Z" clip-rule="evenodd" />
                            </svg>
                            <input type="search" name="q" value="{{ $search }}" class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-10 text-sm text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" placeholder="AI ara">
                            @if ($search !== '')
                                <a href="{{ route('admin.ai.index') }}" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" aria-label="Aramayi temizle">&times;</a>
                            @endif
                        </form>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">{{ $personalar->count() }} kayit</span>
                    </div>
                </div>
            </div>

            @if ($personalar->isEmpty())
                <div class="px-5 py-10 text-sm text-gray-500">
                    @if ($search !== '')
                        "{{ $search }}" icin kayit yok.
                    @else
                        Henuz AI kullanici yok.
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">AI kullanici</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Dil</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Durum</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Kanal</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Islem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($personalar as $personaUser)
                                @php $persona = $personaUser->aiPersonaProfile; @endphp
                                <tr class="transition-colors hover:bg-gray-50">
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.ai.goster', $personaUser) }}" class="flex items-start gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">
                                                {{ mb_substr((string) $personaUser->ad, 0, 1) }}{{ mb_substr((string) $personaUser->soyad, 0, 1) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-gray-900">{{ $personaUser->ad }} {{ $personaUser->soyad }}</div>
                                                <div class="text-xs text-gray-500">{{ '@' . $personaUser->kullanici_adi }}</div>
                                                <div class="mt-1 max-w-lg text-xs leading-5 text-gray-500">{{ \Illuminate\Support\Str::limit($persona?->persona_ozeti ?: ($personaUser->biyografi ?: '-'), 90) }}</div>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        <div class="font-medium text-gray-900">{{ $persona?->ana_dil_adi ?: '-' }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ $persona?->persona_ulke ?: ($personaUser->ulke ?: '-') }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($persona?->aktif_mi)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-yellow-500"></span> Pasif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        <div>{{ $persona?->dating_aktif_mi ? 'Dating acik' : 'Dating kapali' }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ $persona?->instagram_aktif_mi ? 'Instagram acik' : 'Instagram kapali' }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <a href="{{ route('admin.ai.goster', $personaUser) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Ac</a>
                                            <a href="{{ route('admin.ai.duzenle', $personaUser) }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">Duzenle</a>
                                            <form method="POST" action="{{ route('admin.ai.sil', $personaUser) }}" onsubmit="return confirm('Bu AI kullanicisini ve bagli verilerini silmek istediginize emin misiniz?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Sil</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">3. Adim</div>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900">Son kayitlar</h2>
                        <p class="mt-1 text-sm text-gray-500">En son olusan AI cevaplarina hizli bakis.</p>
                    </div>
                    <a href="{{ route('admin.ai.traces') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">Tum kayitlari ac</a>
                </div>
            </div>

            @if ($sonTraceler->isEmpty())
                <div class="px-5 py-10 text-sm text-gray-500">Kayit yok.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">AI</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Durum</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Model</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Ozet</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($sonTraceler as $trace)
                                @php
                                    $traceBadge = match ($trace->durum) {
                                        'completed' => 'bg-green-100 text-green-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        'processing' => 'bg-yellow-100 text-yellow-700',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <tr class="transition-colors hover:bg-gray-50">
                                    <td class="px-4 py-4 align-top">
                                        <div class="text-sm font-medium text-gray-900">{{ $trace->aiUser?->ad }} {{ $trace->aiUser?->soyad }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ $trace->kanal }} / {{ optional($trace->created_at)->format('d.m.Y H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $traceBadge }}">{{ $trace->durum }}</span>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700">{{ $trace->model_adi ?: '-' }}</td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($trace->cevap_metni ?: '-', 120) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
