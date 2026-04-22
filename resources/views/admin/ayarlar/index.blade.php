@extends('admin.layout.ana')

@section('baslik', 'Sistem Ayarları')

@section('icerik')
    <div x-data="{ aktifTab: 'genel' }" class="p-6">
        {{-- Tab Başlıkları --}}
        <div class="border-b border-gray-200 mb-6 overflow-x-auto">
            <nav class="flex space-x-1 min-w-max" aria-label="Tabs">
                @foreach ($gruplar as $gKod => $gAd)
                    <button @click="aktifTab = '{{ $gKod }}'"
                        :class="aktifTab === '{{ $gKod }}' ? 'border-indigo-500 text-indigo-600' :
                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition-colors">
                        {{ $gAd }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Tab İçerikleri --}}
        <form method="POST" action="{{ route('admin.ayarlar.guncelle') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @php
                $modelSecenekleri = [
                    'gemini_varsayilan_model' => [
                        'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                    ],
                    'openai_varsayilan_model' => [
                        'gpt-4.1-nano' => 'GPT-4.1 Nano',
                        'gpt-4.1-mini' => 'GPT-4.1 Mini',
                        'gpt-4o-mini' => 'GPT-4o Mini',
                        'gpt-4o' => 'GPT-4o',
                    ],
                    'varsayilan_ai_saglayici' => [
                        'gemini' => 'Google Gemini',
                        'openai' => 'OpenAI',
                    ],
                    'yedek_ai_saglayici' => [
                        'gemini' => 'Google Gemini',
                        'openai' => 'OpenAI',
                    ],
                ];
                $dosyaAyarlari = [
                    'uygulama_logosu',
                    'flutter_logosu',
                    'apple_private_key_path',
                    'google_play_service_account_path',
                    'firebase_service_account_path',
                ];
            @endphp

            @foreach ($gruplar as $gKod => $gAd)
                <div x-show="aktifTab === '{{ $gKod }}'" x-cloak>
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="mb-6 text-lg font-semibold text-gray-900">{{ $gAd }}</h3>

                        @if ($gKod === 'bildirimler')
                            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                Firebase bildirimleri artik <strong>Server Key</strong> ile degil,
                                <strong>HTTP v1 Service Account JSON</strong> ile calisiyor.
                                Bildirimler icin <code>Firebase Project ID</code> ve
                                <code>Firebase Service Account JSON</code> alanlarini kullan.
                            </div>
                        @endif

                        @if (empty($ayarlar[$gKod]))
                            <p class="text-sm text-gray-500">Bu grupta henüz ayar tanımlanmamış.</p>
                        @else
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                @foreach ($ayarlar[$gKod] as $anahtar => $ayar)
                                    @continue($anahtar === 'firebase_server_key')
                                    <div
                                        class="{{ $ayar['tip'] === 'text' || $ayar['tip'] === 'json' ? 'sm:col-span-2' : '' }}">
                                        <label for="{{ $anahtar }}" class="block text-sm font-medium text-gray-700">
                                            {{ $ayar['aciklama'] ?? $anahtar }}
                                        </label>

                                        @if ($ayar['tip'] === 'file' || in_array($anahtar, $dosyaAyarlari))
                                            {{-- Dosya yükleme alanı --}}
                                            @php $resimAlanlari = ['uygulama_logosu', 'flutter_logosu']; @endphp
                                            <div class="mt-1" x-data="{
                                                onizleme: '{{ in_array($anahtar, $resimAlanlari) && $ayar['deger'] ? asset('storage/' . $ayar['deger']) : '' }}',
                                                dosyaAdi: '',
                                                surukleniyor: false,
                                                dosyaSec(e) {
                                                    const f = e.target.files[0];
                                                    if (!f) return;
                                                    this.dosyaAdi = f.name;
                                                    @if (in_array($anahtar, $resimAlanlari)) const r = new FileReader();
                                                            r.onload = ev => this.onizleme = ev.target.result;
                                                            r.readAsDataURL(f); @endif
                                                },
                                                birakildi(e) {
                                                    this.surukleniyor = false;
                                                    const f = e.dataTransfer.files[0];
                                                    if (!f) return;
                                                    this.$refs.girdi.files = e.dataTransfer.files;
                                                    this.dosyaAdi = f.name;
                                                    @if (in_array($anahtar, $resimAlanlari)) const r = new FileReader();
                                                            r.onload = ev => this.onizleme = ev.target.result;
                                                            r.readAsDataURL(f); @endif
                                                }
                                            }">
                                                {{-- Önizleme --}}
                                                @if (in_array($anahtar, $resimAlanlari))
                                                    <div class="mb-3 flex items-center gap-3" x-show="onizleme" x-cloak>
                                                        <img :src="onizleme"
                                                            class="h-16 w-16 rounded-lg border border-gray-200 bg-gray-50 object-contain p-1 shadow-sm">
                                                        <span class="text-xs text-gray-500"
                                                            x-text="dosyaAdi || 'Mevcut logo'"></span>
                                                    </div>
                                                @endif

                                                {{-- Sürükle-bırak alanı --}}
                                                <label for="{{ $anahtar }}"
                                                    class="relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-6 transition-colors"
                                                    :class="surukleniyor ? 'border-indigo-500 bg-indigo-50' :
                                                        'border-gray-300 bg-gray-50 hover:border-indigo-400 hover:bg-indigo-50/50'"
                                                    @dragover.prevent="surukleniyor = true"
                                                    @dragleave.prevent="surukleniyor = false"
                                                    @drop.prevent="birakildi($event)">

                                                    <div class="flex flex-col items-center text-center">
                                                        <svg class="mb-2 h-8 w-8 transition-colors"
                                                            :class="surukleniyor ? 'text-indigo-500' : 'text-gray-400'"
                                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="1.5"
                                                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                        </svg>
                                                        <p class="text-sm text-gray-600">
                                                            <span class="font-semibold text-indigo-600">Dosya seç</span>
                                                            <span class="text-gray-500"> veya sürükle-bırak</span>
                                                        </p>
                                                        <p class="mt-1 text-xs text-gray-400">
                                                            @if (in_array($anahtar, $resimAlanlari))
                                                                PNG, JPG, SVG — Maks 5MB
                                                            @else
                                                                Maks 5MB
                                                            @endif
                                                        </p>
                                                    </div>

                                                    <input type="file" name="{{ $anahtar }}"
                                                        id="{{ $anahtar }}" x-ref="girdi"
                                                        @if (in_array($anahtar, $resimAlanlari)) accept="image/*" @endif
                                                        @if ($anahtar === 'firebase_service_account_path') accept=".json,application/json" @endif
                                                        @change="dosyaSec($event)" class="sr-only">
                                                </label>

                                                {{-- Seçili dosya adı (resim değilse) --}}
                                                @if (!in_array($anahtar, $resimAlanlari))
                                                    <template x-if="dosyaAdi">
                                                        <p class="mt-2 flex items-center gap-1.5 text-xs text-green-600">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span x-text="dosyaAdi"></span>
                                                        </p>
                                                    </template>
                                                @endif

                                                @if ($ayar['deger'] && !in_array($anahtar, ['uygulama_logosu', 'flutter_logosu']))
                                                    <p class="mt-1.5 flex items-center gap-1 text-xs text-green-600">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                        </svg>
                                                        Mevcut: {{ basename($ayar['deger']) }}
                                                    </p>
                                                @endif
                                            </div>
                                        @elseif (isset($modelSecenekleri[$anahtar]))
                                            {{-- Select/Dropdown alanı --}}
                                            <select name="{{ $anahtar }}" id="{{ $anahtar }}"
                                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
                                                @foreach ($modelSecenekleri[$anahtar] as $deger => $etiket)
                                                    <option value="{{ $deger }}"
                                                        {{ $ayar['deger'] == $deger ? 'selected' : '' }}>
                                                        {{ $etiket }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif ($ayar['tip'] === 'boolean')
                                            <div class="mt-1">
                                                <label class="relative inline-flex cursor-pointer items-center">
                                                    <input type="hidden" name="{{ $anahtar }}" value="0">
                                                    <input type="checkbox" name="{{ $anahtar }}" value="1"
                                                        {{ $ayar['deger'] ? 'checked' : '' }} class="peer sr-only">
                                                    <div
                                                        class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-indigo-600 peer-checked:after:translate-x-full">
                                                    </div>
                                                </label>
                                            </div>
                                        @elseif ($ayar['tip'] === 'integer')
                                            <input type="number" name="{{ $anahtar }}" id="{{ $anahtar }}"
                                                value="{{ $ayar['deger'] }}"
                                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
                                        @elseif ($ayar['tip'] === 'text' || $ayar['tip'] === 'json')
                                            <textarea name="{{ $anahtar }}" id="{{ $anahtar }}" rows="3"
                                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">{{ $ayar['tip'] === 'json' ? json_encode($ayar['deger'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $ayar['deger'] }}</textarea>
                                        @else
                                            @if (str_contains($anahtar, 'password') ||
                                                    str_contains($anahtar, 'secret') ||
                                                    str_contains($anahtar, 'key') ||
                                                    str_contains($anahtar, 'sifre'))
                                                <input type="password" name="{{ $anahtar }}" id="{{ $anahtar }}"
                                                    value="{{ $ayar['deger'] }}"
                                                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
                                            @else
                                                <input type="text" name="{{ $anahtar }}" id="{{ $anahtar }}"
                                                    value="{{ $ayar['deger'] }}"
                                                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none">
                                            @endif
                                        @endif

                                        <p class="mt-1 text-xs text-gray-400">{{ $anahtar }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="mt-6 flex justify-end">
                <button type="submit"
                    class="rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Ayarları Kaydet
                </button>
            </div>
        </form>
    </div>
@endsection
