@extends('admin.layout.ana')

@section('baslik', 'Mesajlar - @' . ($instagramKisi->instagram_kullanici_adi ?? $instagramKisi->kullanici_adi))

@section('icerik')
    @php
        $instagramKisiKullaniciAdi = $instagramKisi->instagram_kullanici_adi ?? $instagramKisi->kullanici_adi;
        $instagramKisiGorunenAd = $instagramKisi->gorunen_ad ?: $instagramKisiKullaniciAdi;
        $instagramKisiProfilResmi = $instagramKisi->profil_fotografi_url ?? $instagramKisi->profil_resmi;
    @endphp

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.instagram.kisiler', $instagramHesap) }}"
                    class="rounded-lg bg-gray-100 p-2 text-gray-600 hover:bg-gray-200">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div class="flex items-center gap-3">
                    @if ($instagramKisiProfilResmi)
                        <img src="{{ $instagramKisiProfilResmi }}" alt=""
                            class="h-10 w-10 rounded-full object-cover">
                    @else
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-sm font-bold text-gray-500">
                            {{ mb_strtoupper(mb_substr($instagramKisiKullaniciAdi, 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">{{ $instagramKisiGorunenAd }}</h2>
                        <p class="text-sm text-gray-500">{{ '@' }}{{ $instagramKisiKullaniciAdi }} ·
                            {{ '@' }}{{ $instagramHesap->instagram_kullanici_adi }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <p class="text-sm text-gray-500">{{ $mesajlar->total() }} mesaj</p>

                @if ($mesajlar->total() > 0)
                    <form method="POST"
                        action="{{ route('admin.instagram.kisi-verilerini-sil', [$instagramHesap, $instagramKisi]) }}"
                        onsubmit="return confirm('Bu kisiye ait tum mesajlar, AI gorevleri ve hafizalar kalici olarak silinecek. Emin misiniz?')"
                        class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-100">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            Tum Verileri Sil
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('basari'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                {{ session('basari') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 rounded-xl border border-gray-200 bg-white p-4">
                @if ($mesajlar->hasPages())
                    <div class="mb-4">
                        {{ $mesajlar->links() }}
                    </div>
                @endif

                <div class="space-y-3">
                    @forelse ($mesajlar->reverse() as $mesaj)
                        @php
                            $bendenMi = $mesaj->gonderen_tipi === 'biz';
                            $aiMi = $mesaj->gonderen_tipi === 'ai';
                        @endphp
                        <div class="flex {{ $bendenMi || $aiMi ? 'justify-end' : 'justify-start' }}">
                            <div
                                class="max-w-[70%] rounded-2xl px-4 py-2.5 {{ $bendenMi ? 'bg-indigo-600 text-white' : ($aiMi ? 'bg-violet-100 text-violet-900' : 'bg-gray-100 text-gray-900') }}">
                                @if ($aiMi)
                                    <div class="mb-1 flex items-center gap-1">
                                        <svg class="h-3 w-3 text-violet-500" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                        </svg>
                                        <span class="text-[10px] font-medium text-violet-500">AI
                                            Yanit{{ $mesaj->gonderildi_mi ? '' : ' (Gonderilmedi)' }}</span>
                                    </div>
                                @endif

                                @if ($mesaj->mesaj_tipi === 'ses')
                                    <div
                                        class="mb-1 flex items-center gap-1 {{ $bendenMi ? 'text-indigo-200' : 'text-gray-400' }}">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />
                                        </svg>
                                        <span class="text-xs">Ses mesaji</span>
                                    </div>
                                @elseif ($mesaj->mesaj_tipi === 'foto')
                                    <div
                                        class="mb-1 flex items-center gap-1 {{ $bendenMi ? 'text-indigo-200' : 'text-gray-400' }}">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M2.25 15.75V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-2.25" />
                                        </svg>
                                        <span class="text-xs">Fotograf</span>
                                    </div>
                                @endif

                                @if ($mesaj->mesaj_metni)
                                    <p class="text-sm whitespace-pre-wrap">{{ $mesaj->mesaj_metni }}</p>
                                @endif

                                <p
                                    class="mt-1 text-[10px] {{ $bendenMi ? 'text-indigo-200' : ($aiMi ? 'text-violet-400' : 'text-gray-400') }}">
                                    {{ $mesaj->created_at->format('d.m.Y H:i') }}
                                    @if ($mesaj->instagram_mesaj_kodu)
                                        · <span class="font-mono">{{ Str::limit($mesaj->instagram_mesaj_kodu, 12) }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-sm text-gray-500">
                            Bu kisiyle henuz mesaj yok.
                        </div>
                    @endforelse
                </div>

                @if ($mesajlar->hasPages())
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        {{ $mesajlar->links() }}
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                @if ($hafizaPaneli)
                    @include('admin.partials.ai-hafiza-paneli', ['panel' => $hafizaPaneli])
                @else
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <h3 class="text-sm font-semibold text-gray-900">AI Hafizasi</h3>
                        <p class="mt-2 text-sm text-gray-500">Bu kisi icin henuz aktif hafiza kaydi yok.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
