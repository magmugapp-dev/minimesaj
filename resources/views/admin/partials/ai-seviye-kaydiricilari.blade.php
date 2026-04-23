@php
    $kaydiricilar = [
        [
            'alan' => 'emoji_seviyesi',
            'etiket' => 'Emoji kullanımı',
        ],
        [
            'alan' => 'flort_seviyesi',
            'etiket' => 'Flört düzeyi',
        ],
        [
            'alan' => 'giriskenlik_seviyesi',
            'etiket' => 'Girişkenlik',
        ],
        [
            'alan' => 'utangaclik_seviyesi',
            'etiket' => 'Utangaçlık',
        ],
        [
            'alan' => 'duygusallik_seviyesi',
            'etiket' => 'Duygusallık',
        ],
        [
            'alan' => 'kiskanclik_seviyesi',
            'etiket' => 'Kıskançlık',
        ],
        [
            'alan' => 'mizah_seviyesi',
            'etiket' => 'Mizah',
        ],
        [
            'alan' => 'zeka_seviyesi',
            'etiket' => 'Zeka',
        ],
    ];
@endphp

<div class="studio-slider-grid">
    @foreach ($kaydiricilar as $kaydirici)
        @php
            $deger = (int) old($kaydirici['alan'], $ayar->{$kaydirici['alan']} ?? 5);
        @endphp
        <div x-data="{ value: {{ $deger }} }" class="studio-slider">
            <div class="studio-slider__top">
                <p class="studio-slider__title">{{ $kaydirici['etiket'] }}</p>
                <div class="studio-slider__value">
                    <span x-text="value"></span>
                </div>
            </div>
            <input type="range" name="{{ $kaydirici['alan'] }}" min="0" max="10" x-model="value"
                class="studio-range">
            <div class="studio-range__legend">
                <span>Düşük</span>
                <span>Yüksek</span>
            </div>
        </div>
    @endforeach
</div>
