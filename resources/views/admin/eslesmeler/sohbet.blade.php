@extends('admin.layout.ana')

@section('baslik', 'Sohbet - ' . ($eslesme->user?->ad ?? '?') . ' & ' . ($eslesme->eslesenUser?->ad ?? '?'))

@section('icerik')
    <div class="space-y-4 p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.eslesmeler.index') }}"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                    Eslesmeler
                </a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('admin.eslesmeler.goster', $eslesme) }}"
                    class="text-sm text-gray-500 hover:text-gray-700">#{{ $eslesme->id }}</a>
                <span class="text-gray-300">/</span>
                <span class="text-sm font-medium text-gray-700">Sohbet</span>
            </div>
            <a href="{{ route('admin.eslesmeler.goster', $eslesme) }}"
                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                Eslesme Detayi
            </a>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-600">
                            {{ $eslesme->user ? mb_substr($eslesme->user->ad, 0, 1) : '?' }}
                        </div>
                        @if ($eslesme->user)
                            <div>
                                <a href="{{ route('admin.eslesmeler.kisi-hafiza', [$eslesme, $eslesme->user]) }}"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $eslesme->user->ad }} {{ $eslesme->user->soyad }}
                                </a>
                                <div class="flex items-center gap-2 text-[11px]">
                                    <span class="text-gray-400">{{ ucfirst($eslesme->user->hesap_tipi) }}</span>
                                    <a href="{{ route('admin.kullanicilar.goster', $eslesme->user) }}"
                                        class="font-medium text-gray-500 hover:text-indigo-700">Profil</a>
                                </div>
                            </div>
                        @endif
                    </div>

                    <svg class="h-5 w-5 text-pink-400" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>

                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-pink-100 text-sm font-bold text-pink-600">
                            {{ $eslesme->eslesenUser ? mb_substr($eslesme->eslesenUser->ad, 0, 1) : '?' }}
                        </div>
                        @if ($eslesme->eslesenUser)
                            <div>
                                <a href="{{ route('admin.eslesmeler.kisi-hafiza', [$eslesme, $eslesme->eslesenUser]) }}"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $eslesme->eslesenUser->ad }} {{ $eslesme->eslesenUser->soyad }}
                                </a>
                                <div class="flex items-center gap-2 text-[11px]">
                                    <span class="text-gray-400">{{ ucfirst($eslesme->eslesenUser->hesap_tipi) }}</span>
                                    <a href="{{ route('admin.kullanicilar.goster', $eslesme->eslesenUser) }}"
                                        class="font-medium text-gray-500 hover:text-indigo-700">Profil</a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <span>{{ $eslesme->sohbet->toplam_mesaj_sayisi ?? 0 }} mesaj</span>
                    <span class="text-gray-300">|</span>
                    <span>Sohbet: {{ ucfirst($eslesme->sohbet->durum) }}</span>
                    @if ($eslesme->sohbet->son_mesaj_tarihi)
                        <span class="text-gray-300">|</span>
                        <span>Son: {{ $eslesme->sohbet->son_mesaj_tarihi->format('d.m.Y H:i') }}</span>
                    @endif
                </div>
            </div>
        </div>

        @if (!empty($hafizaOzetleri))
            <div class="grid gap-4 xl:grid-cols-2">
                @foreach ($hafizaOzetleri as $ozet)
                    @foreach ($ozet['paneller'] as $panel)
                        @include('admin.partials.ai-hafiza-paneli', ['panel' => $panel, 'compact' => true])
                    @endforeach
                @endforeach
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white">
            @if ($mesajlar->total() > 0)
                @if ($mesajlar->hasPages())
                    <div class="border-b border-gray-100 px-4 py-3">
                        {{ $mesajlar->links() }}
                    </div>
                @endif

                <div class="space-y-3 p-4" style="min-height: 300px;">
                    @foreach ($mesajlar->reverse() as $mesaj)
                        @php
                            $solTaraf = $mesaj->gonderen_user_id === $eslesme->user_id;
                        @endphp
                        <div class="flex {{ $solTaraf ? 'justify-start' : 'justify-end' }}">
                            <div
                                class="max-w-[70%] rounded-2xl px-4 py-2.5 {{ $solTaraf ? 'rounded-bl-sm bg-gray-100 text-gray-800' : 'rounded-br-sm bg-indigo-600 text-white' }}">
                                <p
                                    class="mb-0.5 text-[10px] font-semibold {{ $solTaraf ? 'text-gray-500' : 'text-indigo-200' }}">
                                    {{ $mesaj->gonderen ? $mesaj->gonderen->ad : 'Silinmis' }}
                                    @if ($mesaj->ai_tarafindan_uretildi_mi)
                                        <span
                                            class="ml-1 inline-flex items-center rounded bg-violet-200 px-1 py-0 text-[9px] font-bold text-violet-700">AI</span>
                                    @endif
                                </p>

                                @if ($mesaj->silindi_mi || $mesaj->herkesten_silindi_mi)
                                    <p class="text-sm italic {{ $solTaraf ? 'text-gray-400' : 'text-indigo-300' }}">Bu
                                        mesaj silindi</p>
                                @elseif ($mesaj->mesaj_tipi === 'metin')
                                    <p class="text-sm whitespace-pre-wrap break-words">{{ $mesaj->mesaj_metni }}</p>
                                @elseif ($mesaj->mesaj_tipi === 'ses')
                                    <p class="text-sm italic">Sesli mesaj
                                        ({{ $mesaj->dosya_suresi ? $mesaj->dosya_suresi . 's' : '-' }})</p>
                                @elseif ($mesaj->mesaj_tipi === 'foto')
                                    <p class="text-sm italic">Fotograf</p>
                                @elseif ($mesaj->mesaj_tipi === 'sistem')
                                    <p class="text-sm italic">{{ $mesaj->mesaj_metni }}</p>
                                @endif

                                <div
                                    class="mt-1 flex items-center gap-1.5 {{ $solTaraf ? 'justify-start' : 'justify-end' }}">
                                    <span
                                        class="text-[10px] {{ $solTaraf ? 'text-gray-400' : 'text-indigo-200' }}">{{ $mesaj->created_at->format('d.m.Y H:i') }}</span>
                                    @if ($mesaj->okundu_mu)
                                        <svg class="h-3 w-3 {{ $solTaraf ? 'text-blue-500' : 'text-indigo-200' }}"
                                            fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($mesajlar->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">
                        {{ $mesajlar->links() }}
                    </div>
                @endif
            @else
                <div class="px-4 py-16 text-center text-gray-400">
                    Bu sohbette henuz mesaj yok.
                </div>
            @endif
        </div>
    </div>
@endsection
