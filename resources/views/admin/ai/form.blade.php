@extends('admin.layout.ana')

@section('baslik', $character ? 'AI Duzenle' : 'AI Ekle')

@php
    $f = fn (string $key, mixed $default = null) => old($key, data_get($formData, $key, $default));
    $sectionFields = [
        'identity'    => ['active', 'character_id', 'character_version', 'schema_version', 'first_name', 'last_name', 'username', 'gender', 'birth_year', 'country', 'city', 'district', 'quality_tag'],
        'profile'     => ['primary_language_code', 'primary_language_name', 'tagline', 'occupation', 'hobbies', 'warmth', 'dominance', 'humor', 'openness', 'flirtiness', 'intelligence', 'profile_image'],
        'messaging'   => ['average_message_length', 'message_length_min', 'message_length_max', 'can_send_voice', 'can_send_photo', 'timezone', 'sleep_start_weekday', 'sleep_end_weekday', 'sleep_start_weekend', 'sleep_end_weekend'],
        'limits'      => ['daily_chat_limit', 'per_user_daily_message_limit', 'min_response_seconds', 'max_response_seconds', 'model_name', 'temperature', 'top_p', 'max_output_tokens'],
        'reengagement'=> ['reengagement_active', 'reengagement_after_hours', 'reengagement_daily_limit', 'reengagement_templates'],
    ];
    $initialSection = collect($sectionFields)->search(fn ($fields) => collect($fields)->contains(fn ($field) => $errors->has($field))) ?: 'identity';
    $navItems = [
        'identity'     => ['label' => 'Temel Kimlik',      'icon' => 'fingerprint'],
        'profile'      => ['label' => 'Profil & Kisilik',  'icon' => 'person'],
        'messaging'    => ['label' => 'Mesaj Davranisi',   'icon' => 'chat'],
        'limits'       => ['label' => 'Limitler & Model',  'icon' => 'sliders'],
        'reengagement' => ['label' => 'Yeniden Yazma',     'icon' => 'arrow-repeat'],
    ];
    $genderOptions = [
        'female' => ['label' => 'Female', 'description' => 'Kadin karakter olarak davranir.'],
        'male'   => ['label' => 'Male',   'description' => 'Erkek karakter olarak davranir.'],
        'other'  => ['label' => 'Other',  'description' => 'Belirtmek istemeyen veya notr karakter.'],
    ];
    $traitOptions = [
        'warmth' => [
            'warm'     => ['label' => 'Warm',     'description' => 'Sicak, yakin ve kolay iletisim kuran ton.'],
            'neutral'  => ['label' => 'Neutral',  'description' => 'Dengeli, guvenli ton.'],
            'reserved' => ['label' => 'Reserved', 'description' => 'Mesafeli, yavas acilan.'],
        ],
        'dominance' => [
            'soft'      => ['label' => 'Soft',      'description' => 'Uyumlu, baskin olmayan.'],
            'balanced'  => ['label' => 'Balanced',  'description' => 'Dogal denge.'],
            'assertive' => ['label' => 'Assertive', 'description' => 'Net, kararli ton.'],
        ],
        'humor' => [
            'gentle'  => ['label' => 'Gentle',  'description' => 'Hafif, kibar mizah.'],
            'witty'   => ['label' => 'Witty',   'description' => 'Zeki, kivrak espriler.'],
            'playful' => ['label' => 'Playful', 'description' => 'Oyuncu, enerjik.'],
            'dry'     => ['label' => 'Dry',     'description' => 'Ince, ciddi gorunen.'],
        ],
        'openness' => [
            'private'   => ['label' => 'Private',   'description' => 'Az bahseder.'],
            'selective' => ['label' => 'Selective', 'description' => 'Guven olustukca paylasir.'],
            'open'      => ['label' => 'Open',      'description' => 'Acik, paylasimci.'],
        ],
        'flirtiness' => [
            'none'    => ['label' => 'None',    'description' => 'Flortoz olmayan ton.'],
            'mild'    => ['label' => 'Mild',    'description' => 'Hafif, dozunda.'],
            'playful' => ['label' => 'Playful', 'description' => 'Oyuncu, belirgin.'],
            'bold'    => ['label' => 'Bold',    'description' => 'Cesur ama sinirli.'],
        ],
        'intelligence' => [
            'average'  => ['label' => 'Average',  'description' => 'Sade, rahat konusan.'],
            'curious'  => ['label' => 'Curious',  'description' => 'Soru soran, ilgili.'],
            'sharp'    => ['label' => 'Sharp',    'description' => 'Analitik, keskin cevapli.'],
        ],
    ];
    $initialValues = [
        'first_name'           => $f('first_name', ''),
        'username'             => $f('username', ''),
        'city'                 => $f('city', ''),
        'model_name'           => $f('model_name', 'gemini-2.5-flash'),
        'min_response_seconds' => $f('min_response_seconds', 3),
        'max_response_seconds' => $f('max_response_seconds', 30),
        'active'               => (bool) old('active', $character?->active ?? true),
    ];
@endphp

@section('icerik')
    <form method="POST"
        enctype="multipart/form-data"
        action="{{ $character ? route('admin.ai.guncelle', $character) : route('admin.ai.kaydet') }}"
        @submit="if (!validateAll()) $event.preventDefault()"
        x-data="{
            activeSection: '{{ $initialSection }}',
            values: @js($initialValues),
            setSection(s) { this.activeSection = s; this.$nextTick(() => this.$refs.formTop?.scrollIntoView({ behavior: 'smooth', block: 'start' })); },
            validateAll() {
                const fields = [...this.$el.querySelectorAll('input, select, textarea')];
                for (const field of fields) {
                    if (!field.checkValidity()) {
                        const sec = field.closest('[data-section]');
                        if (sec) this.activeSection = sec.dataset.section;
                        this.$nextTick(() => field.reportValidity());
                        return false;
                    }
                }
                return true;
            }
        }">
        @csrf
        @if ($character)
            @method('PUT')
        @endif

        <div x-ref="formTop" class="rounded-lg border border-gray-200 bg-white shadow">
            {{-- Header --}}
            <div class="border-b border-gray-200 p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-widest text-indigo-600">AI Studio</div>
                        <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ $character ? 'AI karakter duzenle' : 'Yeni AI karakter' }}</h1>
                        <p class="mt-1 max-w-xl text-sm text-gray-500">Soldaki menuden bolum secin, alanlari doldurup kaydet.</p>
                    </div>
                    <label class="inline-flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-800">
                        <input type="checkbox" name="active" value="1" x-model="values.active" @checked(old('active', $character?->active ?? true)) class="rounded border-gray-300 text-indigo-600">
                        Aktif karakter
                        <span class="text-xs font-medium text-gray-500">Kesfet ve sohbet akisi icin kullanilir.</span>
                    </label>
                </div>
            </div>

            @if ($errors->any())
                <div class="border-b border-red-200 bg-red-50 px-5 py-3 text-sm font-medium text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Split layout --}}
            <div class="studio-split p-5">

                {{-- Sidebar --}}
                <aside class="studio-split-sidebar">
                    {{-- Avatar + name --}}
                    <div class="flex items-center gap-3">
                        <div class="studio-split-avatar" x-text="(values.first_name || 'A').slice(0,1)"></div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-gray-900" x-text="values.first_name || 'Isimsiz karakter'"></div>
                            <div class="truncate text-xs text-gray-500" x-text="values.username ? '@' + values.username : '@kullanici_adi'"></div>
                        </div>
                    </div>

                    {{-- Status badge --}}
                    <div class="mt-3 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="values.active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                            <span class="inline-block h-1.5 w-1.5 rounded-full" :class="values.active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                            <span x-text="values.active ? 'Aktif' : 'Pasif'"></span>
                        </span>
                    </div>

                    {{-- Nav --}}
                    <nav class="mt-4 space-y-0.5">
                        @foreach ($navItems as $key => $item)
                            <div class="studio-split-nav-item" :class="activeSection === '{{ $key }}' && 'active'" @click="setSection('{{ $key }}')">
                                <span class="studio-split-nav-item__dot"></span>
                                {{ $item['label'] }}
                            </div>
                        @endforeach
                    </nav>

                    {{-- Mini summary --}}
                    <div class="mt-5 space-y-2 border-t border-gray-100 pt-4 text-xs">
                        <div class="flex justify-between gap-2 text-gray-500">
                            <span>Cevap</span>
                            <span class="font-semibold text-gray-900" x-text="`${values.min_response_seconds||0}–${values.max_response_seconds||0} sn`"></span>
                        </div>
                        <div class="flex justify-between gap-2 text-gray-500">
                            <span>Model</span>
                            <span class="max-w-32 truncate text-right font-semibold text-gray-900" x-text="values.model_name||'-'"></span>
                        </div>
                        <div class="flex justify-between gap-2 text-gray-500">
                            <span>Sehir</span>
                            <span class="font-semibold text-gray-900" x-text="values.city||'-'"></span>
                        </div>
                    </div>

                    {{-- Submit button (sidebar) --}}
                    <div class="mt-5 pt-4 border-t border-gray-100">
                        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">
                            Kaydet
                        </button>
                    </div>
                </aside>

                {{-- Main content --}}
                <div class="min-w-0">

                    {{-- Section: Temel Kimlik --}}
                    <div data-section="identity" class="studio-split-section space-y-5" :class="activeSection === 'identity' && 'active'">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Temel kimlik</h2>
                            <p class="mt-1 text-sm text-gray-500">Karakterin sistemdeki kaydi ve profilde gorunen ana bilgileri.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ai-field label="Character ID">
                                <input required name="character_id" value="{{ $f('character_id') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Kullanici adi">
                                <input required name="username" x-model="values.username" value="{{ $f('username') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Ad">
                                <input required name="first_name" x-model="values.first_name" value="{{ $f('first_name') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Soyad">
                                <input name="last_name" value="{{ $f('last_name') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Versiyon">
                                <input required type="number" name="character_version" value="{{ $f('character_version', 1) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Schema">
                                <input required name="schema_version" value="{{ $f('schema_version', 'bv1.0') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Dogum yili">
                                <input required type="number" name="birth_year" value="{{ $f('birth_year') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Kalite etiketi">
                                <input name="quality_tag" value="{{ $f('quality_tag', 'A') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                        </div>

                        <div>
                            <div class="text-sm font-bold text-gray-900">Cinsiyet</div>
                            <div class="mt-2 grid gap-3 md:grid-cols-3">
                                @foreach ($genderOptions as $value => $option)
                                    <label class="cursor-pointer rounded-lg border border-gray-200 p-4 transition hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                                        <input required type="radio" name="gender" value="{{ $value }}" @checked($f('gender', 'female') === $value) class="sr-only">
                                        <span class="block text-sm font-bold text-gray-900">{{ $option['label'] }}</span>
                                        <span class="mt-1 block text-xs leading-5 text-gray-500">{{ $option['description'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ai-field label="Ulke">
                                <input name="country" value="{{ $f('country') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Sehir">
                                <input name="city" x-model="values.city" value="{{ $f('city') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Ilce">
                                <input name="district" value="{{ $f('district') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                        </div>
                    </div>

                    {{-- Section: Profil & Kisilik --}}
                    <div data-section="profile" class="studio-split-section space-y-5" :class="activeSection === 'profile' && 'active'">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Profil ve kisilik</h2>
                            <p class="mt-1 text-sm text-gray-500">Bu alanlar AI prompt karakterini daha tutarli hale getirir.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ai-field label="Dil kodu" hint="Ornek: tr, en">
                                <input required name="primary_language_code" value="{{ $f('primary_language_code', 'tr') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Dil adi" hint="Promptta okunabilir dil adi olarak kullanilir.">
                                <input required name="primary_language_name" value="{{ $f('primary_language_name', 'Turkish') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Tagline" class="sm:col-span-2">
                                <input name="tagline" value="{{ $f('tagline') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Meslek" class="sm:col-span-2">
                                <input name="occupation" value="{{ $f('occupation') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Hobiler" hint="Her satira bir hobi yazilabilir." class="sm:col-span-2">
                                <textarea name="hobbies" rows="4" class="mt-1 w-full rounded-lg border-gray-300 text-sm">{{ $f('hobbies') }}</textarea>
                            </x-ai-field>
                            <x-ai-field label="Profil fotografi" hint="Yeni fotograf secilmezse mevcut fotograf korunur." class="sm:col-span-2">
                                <input type="file" name="profile_image" accept="image/*" class="mt-1 w-full text-sm">
                            </x-ai-field>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ($traitOptions as $trait => $options)
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <div class="text-sm font-bold text-gray-900">{{ \Illuminate\Support\Str::headline($trait) }}</div>
                                    <div class="mt-3 space-y-2">
                                        @foreach ($options as $value => $option)
                                            <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                                                <input type="radio" name="{{ $trait }}" value="{{ $value }}" @checked($f($trait) === $value) class="mt-1 text-indigo-600">
                                                <span>
                                                    <span class="block text-sm font-semibold text-gray-900">{{ $option['label'] }}</span>
                                                    <span class="block text-xs leading-5 text-gray-500">{{ $option['description'] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Section: Mesaj Davranisi --}}
                    <div data-section="messaging" class="studio-split-section space-y-5" :class="activeSection === 'messaging' && 'active'">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Mesaj davranisi</h2>
                            <p class="mt-1 text-sm text-gray-500">Mesaj uzunlugu, medya yetenekleri ve uyku saatlerini belirle.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ai-field label="Ortalama uzunluk" hint="AI'nin hedefledigi yaklasik karakter sayisi.">
                                <input required type="number" name="average_message_length" value="{{ $f('average_message_length', 60) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Min uzunluk">
                                <input required type="number" name="message_length_min" value="{{ $f('message_length_min', 5) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Max uzunluk">
                                <input required type="number" name="message_length_max" value="{{ $f('message_length_max', 220) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-4 transition hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                                <input type="checkbox" name="can_send_voice" value="1" @checked($f('can_send_voice', false)) class="mt-1 rounded border-gray-300 text-indigo-600">
                                <span>
                                    <span class="block text-sm font-bold text-gray-900">Ses gonderebilir</span>
                                    <span class="block text-xs leading-5 text-gray-500">AI karakter sesli mesaj davranisini promptta kullanabilir.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-4 transition hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                                <input type="checkbox" name="can_send_photo" value="1" @checked($f('can_send_photo', false)) class="mt-1 rounded border-gray-300 text-indigo-600">
                                <span>
                                    <span class="block text-sm font-bold text-gray-900">Fotograf gonderebilir</span>
                                    <span class="block text-xs leading-5 text-gray-500">AI karakter gorsel paylasabilen biri gibi modellenir.</span>
                                </span>
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ai-field label="Timezone" hint="Cevap zamanlamasi ve uyku saatleri icin kullanilir.">
                                <input required name="timezone" value="{{ $f('timezone', 'Europe/Istanbul') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            @foreach (['sleep_start_weekday' => 'Hafta ici uyku baslangic', 'sleep_end_weekday' => 'Hafta ici uyku bitis', 'sleep_start_weekend' => 'Hafta sonu uyku baslangic', 'sleep_end_weekend' => 'Hafta sonu uyku bitis'] as $key => $label)
                                <x-ai-field :label="$label" hint="24 saat formati: 23:30">
                                    <input name="{{ $key }}" value="{{ $f($key) }}" placeholder="23:30" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                </x-ai-field>
                            @endforeach
                        </div>
                    </div>

                    {{-- Section: Limitler & Model --}}
                    <div data-section="limits" class="studio-split-section space-y-5" :class="activeSection === 'limits' && 'active'">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Limitler ve model</h2>
                            <p class="mt-1 text-sm text-gray-500">Cevap hizi ve Gemini davranisini buradan ayarla.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ai-field label="Min cevap saniye" hint="Kullanici mesajinin created_at zamanindan sonra minimum bekleme.">
                                <input required type="number" name="min_response_seconds" x-model="values.min_response_seconds" value="{{ $f('min_response_seconds', 3) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Max cevap saniye" hint="Rastgele cevap gecikmesinin ust siniri.">
                                <input required type="number" name="max_response_seconds" x-model="values.max_response_seconds" value="{{ $f('max_response_seconds', 30) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-sm font-semibold text-emerald-900 sm:col-span-2">
                                Cevap araligi kullanici mesajinin created_at zamanindan sonra
                                <span x-text="values.min_response_seconds || 0"></span>-<span x-text="values.max_response_seconds || 0"></span>
                                saniye olarak planlanir.
                            </div>
                            <x-ai-field label="Gunluk chat limit">
                                <input required type="number" name="daily_chat_limit" value="{{ $f('daily_chat_limit', 100) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Kullanici gunluk limit">
                                <input required type="number" name="per_user_daily_message_limit" value="{{ $f('per_user_daily_message_limit', 50) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Model" hint="Gemini model adi. Ornek: gemini-2.5-flash" class="sm:col-span-2">
                                <input required name="model_name" x-model="values.model_name" value="{{ $f('model_name', 'gemini-2.5-flash') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Temperature" hint="Yuksek deger daha yaratici, dusuk deger daha tutarli cevap verir.">
                                <input required type="number" step="0.01" name="temperature" value="{{ $f('temperature', 0.8) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Top P" hint="Cevap cesitliligini kontrol eder; 0.8-0.95 genelde dengelidir.">
                                <input required type="number" step="0.01" name="top_p" value="{{ $f('top_p', 0.9) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Max token" hint="Tek cevapta uretilebilecek maksimum token siniri." class="sm:col-span-2">
                                <input required type="number" name="max_output_tokens" value="{{ $f('max_output_tokens', 1024) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                        </div>
                    </div>

                    {{-- Section: Yeniden Yazma --}}
                    <div data-section="reengagement" class="studio-split-section space-y-5" :class="activeSection === 'reengagement' && 'active'">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Yeniden yazma</h2>
                            <p class="mt-1 text-sm text-gray-500">Sessizlik suresi dolunca karakter tekrar sohbet baslatabilir.</p>
                        </div>
                        <div class="grid gap-4 lg:grid-cols-3">
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-4 transition hover:border-indigo-300 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                                <input type="checkbox" name="reengagement_active" value="1" @checked($f('reengagement_active', false)) class="mt-1 rounded border-gray-300 text-indigo-600">
                                <span>
                                    <span class="block text-sm font-bold text-gray-900">Tekrar yazma aktif</span>
                                    <span class="block text-xs leading-5 text-gray-500">Sessizlik suresi dolunca karakter tekrar sohbet baslatabilir.</span>
                                </span>
                            </label>
                            <x-ai-field label="Sessizlik saati">
                                <input required type="number" name="reengagement_after_hours" value="{{ $f('reengagement_after_hours', 168) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Gunluk limit">
                                <input required type="number" name="reengagement_daily_limit" value="{{ $f('reengagement_daily_limit', 1) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            </x-ai-field>
                            <x-ai-field label="Re-engagement template JSON" hint="JSON liste formatinda mesaj sablonlari." class="lg:col-span-3">
                                <textarea name="reengagement_templates" rows="5" class="mt-1 w-full rounded-lg border-gray-300 font-mono text-xs">{{ $f('reengagement_templates', '[]') }}</textarea>
                            </x-ai-field>
                        </div>
                    </div>

                </div>{{-- /main content --}}
            </div>{{-- /studio-split --}}

            {{-- Footer --}}
            <div class="flex items-center justify-end border-t border-gray-200 bg-gray-50 px-5 py-4 gap-3">
                <button type="button" @click="setSection('identity')" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">Basa don</button>
                <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">Kaydet</button>
            </div>
        </div>
    </form>
@endsection
