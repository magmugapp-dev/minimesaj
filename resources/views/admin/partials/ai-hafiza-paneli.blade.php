@php
    $compact = $compact ?? false;
@endphp

<div class="rounded-xl border border-violet-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">{{ $panel['baslik'] }}</h3>
            @if (!empty($panel['aciklama']))
                <p class="mt-1 text-xs text-gray-500">{{ $panel['aciklama'] }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-[11px] uppercase tracking-wide text-gray-400">Aktif Hafiza</p>
            <p class="text-lg font-bold text-violet-700">{{ $panel['toplam_kayit'] }}</p>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2 text-[11px] text-gray-500">
        @if (!empty($panel['ai_etiketi']))
            <span class="rounded-full bg-violet-50 px-2.5 py-1 text-violet-700">AI: {{ $panel['ai_etiketi'] }}</span>
        @endif
        @if (!empty($panel['hedef_etiketi']))
            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600">Kisi: {{ $panel['hedef_etiketi'] }}</span>
        @endif
        @if (!empty($panel['son_guncelleme_formatli']))
            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600">Son guncelleme: {{ $panel['son_guncelleme_formatli'] }}</span>
        @endif
    </div>

    <div class="mt-4 space-y-4">
        @foreach ($panel['gruplar'] as $grup)
            @php
                $gorunenKayitlar = $compact ? array_slice($grup['kayitlar'], 0, 3) : $grup['kayitlar'];
                $gizliKayitSayisi = count($grup['kayitlar']) - count($gorunenKayitlar);
            @endphp

            <div class="rounded-lg border border-gray-100">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $grup['etiket'] }}</h4>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600">{{ count($grup['kayitlar']) }}</span>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach ($gorunenKayitlar as $kayit)
                        <div class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-gray-900">{{ $kayit['konu_etiketi'] }}</p>
                                <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700">
                                    Onem {{ $kayit['onem_puani'] }}
                                </span>
                                @if ($kayit['duygu_mu'] && !empty($kayit['son_kullanma_formatli']))
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                        Sureli: {{ $kayit['son_kullanma_formatli'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm leading-6 text-gray-700">{{ $kayit['icerik'] }}</p>
                            @if (!empty($kayit['guncellendi_formatli']))
                                <p class="mt-2 text-[11px] text-gray-400">Guncellendi: {{ $kayit['guncellendi_formatli'] }}</p>
                            @endif
                        </div>
                    @endforeach

                    @if ($compact && $gizliKayitSayisi > 0)
                        <div class="px-4 py-3 text-[11px] font-medium text-gray-500">
                            +{{ $gizliKayitSayisi }} kayit daha tam hafiza ekraninda gorunur.
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
