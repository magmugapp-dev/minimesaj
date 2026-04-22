@php
    $kaydiricilar = [
        [
            'alan' => 'emoji_seviyesi',
            'etiket' => 'Emoji kullanımı',
            'aciklama' => 'Mesajların ne kadar görsel ve sıcak hissettirdiğini belirler.',
        ],
        [
            'alan' => 'flort_seviyesi',
            'etiket' => 'Flört düzeyi',
            'aciklama' => 'Yakınlaşma ve romantik çağrışım dozunu ayarlar.',
        ],
        [
            'alan' => 'giriskenlik_seviyesi',
            'etiket' => 'Girişkenlik',
            'aciklama' => 'İlk adımı atma ve konuşmayı taşıma eğilimini yükseltir.',
        ],
        [
            'alan' => 'utangaclik_seviyesi',
            'etiket' => 'Utangaçlık',
            'aciklama' => 'Daha kontrollü, mesafeli ve çekingen bir hissiyat verir.',
        ],
        [
            'alan' => 'duygusallik_seviyesi',
            'etiket' => 'Duygusallık',
            'aciklama' => 'Empati ve duygusal yoğunluğun ne kadar baskın olacağını tanımlar.',
        ],
        [
            'alan' => 'kiskanclik_seviyesi',
            'etiket' => 'Kıskançlık',
            'aciklama' => 'Sahiplenme ve hassasiyet dozunu dikkatli şekilde yönetir.',
        ],
        [
            'alan' => 'mizah_seviyesi',
            'etiket' => 'Mizah',
            'aciklama' => 'Espri sıklığını ve konuşmanın ne kadar eğlenceli olacağını etkiler.',
        ],
        [
            'alan' => 'zeka_seviyesi',
            'etiket' => 'Zeka',
            'aciklama' => 'Cevapların ne kadar analitik, kıvrak ve sezgisel olacağını belirler.',
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
