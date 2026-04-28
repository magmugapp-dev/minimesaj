@extends('admin.layout.ana')

@section('baslik', $character ? 'AI Duzenle' : 'AI Ekle')

@php
    $f = fn (string $key, mixed $default = null) => old($key, data_get($formData, $key, $default));
    $stepFields = [
        1 => ['active', 'character_id', 'character_version', 'schema_version', 'first_name', 'last_name', 'username', 'gender', 'birth_year', 'country', 'city', 'district'],
        2 => ['primary_language_code', 'primary_language_name', 'tagline', 'occupation', 'hobbies', 'warmth', 'dominance', 'humor', 'openness', 'flirtiness', 'intelligence', 'profile_image'],
        3 => ['average_message_length', 'message_length_min', 'message_length_max', 'can_send_voice', 'can_send_photo', 'timezone', 'sleep_start_weekday', 'sleep_end_weekday', 'sleep_start_weekend', 'sleep_end_weekend'],
        4 => ['daily_chat_limit', 'per_user_daily_message_limit', 'min_response_seconds', 'max_response_seconds', 'model_name', 'temperature', 'top_p', 'max_output_tokens', 'quality_tag'],
        5 => ['reengagement_active', 'reengagement_after_hours', 'reengagement_daily_limit', 'reengagement_templates'],
    ];
    $initialStep = collect($stepFields)->search(fn ($fields) => collect($fields)->contains(fn ($field) => $errors->has($field))) ?: 1;
    $steps = [
        1 => ['title' => 'Temel Kimlik', 'description' => 'Karakterin uygulamadaki gorunen kimligi'],
        2 => ['title' => 'Profil ve Kisilik', 'description' => 'Konusma tonunu ve profili sekillendir'],
        3 => ['title' => 'Mesaj Davranisi', 'description' => 'Mesaj uzunlugu, medya ve uygunluk saatleri'],
        4 => ['title' => 'Limitler ve Model', 'description' => 'Cevap hizi, limitler ve Gemini ayarlari'],
        5 => ['title' => 'Tekrar Yazma ve Ozet', 'description' => 'Son kontrol ve kaydetme'],
    ];
    $genderOptions = [
        'female' => ['label' => 'Female', 'description' => 'Kadin karakter olarak davranir ve profil dili buna gore kurulur.'],
        'male' => ['label' => 'Male', 'description' => 'Erkek karakter olarak davranir ve profil dili buna gore kurulur.'],
        'other' => ['label' => 'Other', 'description' => 'Belirtmek istemeyen veya notr karakter icin kullanilir.'],
    ];
    $traitOptions = [
        'warmth' => [
            'warm' => ['label' => 'Warm', 'description' => 'Sicak, yakin ve kolay iletisim kuran ton.'],
            'neutral' => ['label' => 'Neutral', 'description' => 'Dengeli, abartisiz ve guvenli ton.'],
            'reserved' => ['label' => 'Reserved', 'description' => 'Mesafeli, daha yavas acilan karakter.'],
        ],
        'dominance' => [
            'soft' => ['label' => 'Soft', 'description' => 'Uyumlu, baskin olmayan ve takip eden tavir.'],
            'balanced' => ['label' => 'Balanced', 'description' => 'Ne cok pasif ne cok baskin; dogal denge.'],
            'assertive' => ['label' => 'Assertive', 'description' => 'Net, kararli ve sohbeti yonlendirebilen ton.'],
        ],
        'humor' => [
            'gentle' => ['label' => 'Gentle', 'description' => 'Hafif, kibar ve guvenli mizah.'],
            'witty' => ['label' => 'Witty', 'description' => 'Zeki, kisa ve kivrak espriler.'],
            'playful' => ['label' => 'Playful', 'description' => 'Oyuncu, enerjik ve rahat mizah.'],
            'dry' => ['label' => 'Dry', 'description' => 'Sakin, ince ve ciddi gorunen mizah.'],
        ],
        'openness' => [
            'private' => ['label' => 'Private', 'description' => 'Kendinden az bahseder, daha kapali ilerler.'],
            'selective' => ['label' => 'Selective', 'description' => 'Guven olustukca detay paylasir.'],
            'open' => ['label' => 'Open', 'description' => 'Daha acik, anlatkan ve paylasimci profil.'],
        ],
        'flirtiness' => [
            'none' => ['label' => 'None', 'description' => 'Flortoz olmayan, arkadasca ton.'],
            'mild' => ['label' => 'Mild', 'description' => 'Hafif flort, guvenli ve dozunda.'],
            'playful' => ['label' => 'Playful', 'description' => 'Oyuncu, tatli ve daha belirgin flort.'],
            'bold' => ['label' => 'Bold', 'description' => 'Daha cesur ama sinirli flort tavri.'],
        ],
        'intelligence' => [
            'average' => ['label' => 'Average', 'description' => 'Dogal, sade ve herkesle rahat konusan.'],
            'curious' => ['label' => 'Curious', 'description' => 'Soru soran, ogrenmeye acik ve ilgili.'],
            'sharp' => ['label' => 'Sharp', 'description' => 'Hizli kavrayan, analitik ve keskin cevapli.'],
        ],
    ];
    $initialValues = [
        'first_name' => $f('first_name', ''),
        'username' => $f('username', ''),
        'city' => $f('city', ''),
        'model_name' => $f('model_name', 'gemini-2.5-flash'),
        'min_response_seconds' => $f('min_response_seconds', 3),
        'max_response_seconds' => $f('max_response_seconds', 30),
        'active' => (bool) old('active', $character?->active ?? true),
    ];
@endphp

@section('icerik')
    <form method="POST"
        enctype="multipart/form-data"
        action="{{ $character ? route('admin.ai.guncelle', $character) : route('admin.ai.kaydet') }}"
        class="space-y-6"
        @submit="if (!validateAll()) $event.preventDefault()"
        x-data="{
            step: {{ (int) $initialStep }},
            maxStep: {{ $errors->any() ? count($steps) : (int) $initialStep }},
            total: {{ count($steps) }},
            values: @js($initialValues),
            validateStep() {
                const fields = [...this.$el.querySelectorAll(`[data-step='${this.step}'] input, [data-step='${this.step}'] select, [data-step='${this.step}'] textarea`)];
                for (const field of fields) {
                    if (!field.checkValidity()) {
                        field.reportValidity();
                        return false;
                    }
                }
                return true;
            },
            validateAll() {
                const fields = [...this.$el.querySelectorAll('input, select, textarea')];
                for (const field of fields) {
                    if (!field.checkValidity()) {
                        const section = field.closest('[data-step]');
                        const target = Number(section?.dataset?.step || this.step);
                        this.step = target;
                        this.maxStep = Math.max(this.maxStep, target);
                        this.$nextTick(() => field.reportValidity());
                        return false;
                    }
                }
                return true;
            },
            next() {
                if (!this.validateStep()) return;
                this.step = Math.min(this.total, this.step + 1);
                this.maxStep = Math.max(this.maxStep, this.step);
                this.scrollWizard();
            },
            prev() {
                this.step = Math.max(1, this.step - 1);
                this.scrollWizard();
            },
            go(target) {
                if (target <= this.maxStep) {
                    this.step = target;
                    this.scrollWizard();
                }
            },
            scrollWizard() {
                this.$nextTick(() => this.$refs.wizardTop?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            }
        }">
        @csrf
        @if ($character)
            @method('PUT')
        @endif

        <div x-ref="wizardTop" class="rounded-lg border border-gray-200 bg-white shadow">
            <div class="border-b border-gray-200 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-widest text-indigo-600">AI Wizard</div>
                        <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ $character ? 'AI karakter duzenle' : 'Yeni AI karakter' }}</h1>
                        <p class="mt-2 max-w-2xl text-sm text-gray-500">Karakteri adim adim olustur; teknik ayarlar sona toplandi, kritik seceneklerin yaninda Turkce aciklamalar var.</p>
                    </div>
                    <label class="inline-flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-800">
                        <input type="checkbox" name="active" value="1" x-model="values.active" @checked(old('active', $character?->active ?? true)) class="rounded border-gray-300 text-indigo-600">
                        Aktif karakter
                        <span class="text-xs font-medium text-gray-500">Kesfet ve sohbet akisi icin kullanilir.</span>
                    </label>
                </div>

                <div class="mt-5 lg:hidden">
                    <div class="flex items-center justify-between text-sm font-semibold text-gray-700">
                        <span x-text="`Adim ${step} / ${total}`"></span>
                        <span x-text="['Temel Kimlik','Profil ve Kisilik','Mesaj Davranisi','Limitler ve Model','Tekrar Yazma ve Ozet'][step - 1]"></span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-gray-100">
                        <div class="h-2 rounded-full bg-indigo-600 transition-all" :style="`width: ${(step / total) * 100}%`"></div>
                    </div>
                </div>

                <div class="mt-5 hidden grid-cols-5 gap-2 lg:grid">
                    @foreach ($steps as $index => $meta)
                        <button type="button"
                            @click="go({{ $index }})"
                            class="rounded-lg border px-3 py-3 text-left transition"
                            :class="step === {{ $index }} ? 'border-indigo-600 bg-indigo-50 text-indigo-900' : (maxStep >= {{ $index }} ? 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300' : 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed')">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                                    :class="step === {{ $index }} ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500'">{{ $index }}</span>
                                <span class="text-sm font-bold">{{ $meta['title'] }}</span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-gray-500">{{ $meta['description'] }}</p>
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($errors->any())
                <div class="border-b border-red-200 bg-red-50 px-5 py-3 text-sm font-medium text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid gap-6 p-5 xl:grid-cols-[1fr_320px]">
                <div class="min-w-0">
                    <section x-show="step === 1" x-cloak data-step="1" class="space-y-5">
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
                    </section>

                    <section x-show="step === 2" x-cloak data-step="2" class="space-y-5">
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
                    </section>

                    <section x-show="step === 3" x-cloak data-step="3" class="space-y-5">
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
                    </section>

                    <section x-show="step === 4" x-cloak data-step="4" class="space-y-5">
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
                    </section>

                    <section x-show="step === 5" x-cloak data-step="5" class="space-y-5">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Tekrar yazma ve ozet</h2>
                            <p class="mt-1 text-sm text-gray-500">Son kez kontrol et, sonra karakteri kaydet.</p>
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

                        <div class="rounded-lg border border-indigo-100 bg-indigo-50 p-4">
                            <h3 class="text-sm font-bold text-indigo-950">Kayit ozeti</h3>
                            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                <div><dt class="font-semibold text-indigo-700">Karakter</dt><dd class="text-indigo-950" x-text="values.first_name || 'Isimsiz'"></dd></div>
                                <div><dt class="font-semibold text-indigo-700">Kullanici adi</dt><dd class="text-indigo-950" x-text="values.username || '-'"></dd></div>
                                <div><dt class="font-semibold text-indigo-700">Sehir</dt><dd class="text-indigo-950" x-text="values.city || '-'"></dd></div>
                                <div><dt class="font-semibold text-indigo-700">Model</dt><dd class="text-indigo-950" x-text="values.model_name || '-'"></dd></div>
                                <div><dt class="font-semibold text-indigo-700">Cevap araligi</dt><dd class="text-indigo-950" x-text="`${values.min_response_seconds || 0}-${values.max_response_seconds || 0} saniye`"></dd></div>
                                <div><dt class="font-semibold text-indigo-700">Durum</dt><dd class="text-indigo-950" x-text="values.active ? 'Aktif' : 'Pasif'"></dd></div>
                            </dl>
                        </div>
                    </section>
                </div>

                <aside class="hidden xl:block">
                    <div class="sticky top-24 rounded-lg border border-gray-200 bg-gray-50 p-5">
                        <div class="text-xs font-semibold uppercase tracking-widest text-gray-500">Canli ozet</div>
                        <div class="mt-4 rounded-lg bg-white p-4 shadow-sm">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-lg font-bold text-indigo-700" x-text="(values.first_name || 'A').slice(0, 1)"></div>
                            <h3 class="mt-3 text-lg font-bold text-gray-900" x-text="values.first_name || 'Isimsiz karakter'"></h3>
                            <p class="text-sm text-gray-500" x-text="values.username ? '@' + values.username : '@kullanici_adi'"></p>
                        </div>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Sehir</dt><dd class="font-semibold text-gray-900" x-text="values.city || '-'"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Model</dt><dd class="max-w-40 truncate font-semibold text-gray-900" x-text="values.model_name || '-'"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Cevap</dt><dd class="font-semibold text-gray-900" x-text="`${values.min_response_seconds || 0}-${values.max_response_seconds || 0} sn`"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Durum</dt><dd class="font-semibold" :class="values.active ? 'text-emerald-700' : 'text-gray-500'" x-text="values.active ? 'Aktif' : 'Pasif'"></dd></div>
                        </dl>
                    </div>
                </aside>
            </div>

            <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-5 py-4">
                <button type="button" @click="prev()" x-show="step > 1" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">Geri</button>
                <div x-show="step === 1" class="text-sm text-gray-500">Ilk adimdasin.</div>
                <div class="ml-auto flex gap-2">
                    <button type="button" @click="next()" x-show="step < total" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white">Ileri</button>
                    <button x-show="step === total" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white">Kaydet</button>
                </div>
            </div>
        </div>
    </form>
@endsection
