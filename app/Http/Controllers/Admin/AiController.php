<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAyar;
use App\Models\AiPersonaProfile;
use App\Models\User;
use App\Services\Users\UserAvailabilityScheduleService;
use App\Services\Users\UserOnlineStatusService;
use App\Services\YapayZeka\GeminiSaglayici;
use App\Services\YapayZeka\V2\AiEngineConfigService;
use App\Support\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AiController extends Controller
{
    public function index(Request $request)
    {
        $sorgu = User::where('hesap_tipi', 'ai')->with('aiAyar');

        // Arama
        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('ad', 'like', "%{$arama}%")
                    ->orWhere('soyad', 'like', "%{$arama}%")
                    ->orWhere('kullanici_adi', 'like', "%{$arama}%");
            });
        }

        // Durum filtresi
        if ($durum = $request->input('durum')) {
            $sorgu->where('hesap_durumu', $durum);
        }

        // Aktiflik filtresi (AI aktif mi)
        if ($request->input('aktif') !== null && $request->input('aktif') !== '') {
            $sorgu->whereHas('aiAyar', function ($q) use ($request) {
                $q->where('aktif_mi', $request->boolean('aktif'));
            });
        }

        // Sağlayıcı filtresi
        if ($saglayici = $request->input('saglayici')) {
            $sorgu->whereHas('aiAyar', function ($q) use ($saglayici) {
                $q->where('saglayici_tipi', $saglayici);
            });
        }

        $sorgu->orderBy($request->input('sirala', 'created_at'), $request->input('yon', 'desc') === 'asc' ? 'asc' : 'desc');

        $aiKullanicilar = $sorgu->paginate(25)->withQueryString();

        // Özet istatistikler
        $istatistikler = [
            'toplam' => User::where('hesap_tipi', 'ai')->count(),
            'aktif' => AiAyar::where('aktif_mi', true)->count(),
            'gemini' => AiAyar::where('saglayici_tipi', 'gemini')->count(),
            'openai' => AiAyar::where('saglayici_tipi', 'openai')->count(),
        ];

        return view('admin.ai.index', compact('aiKullanicilar', 'istatistikler'));
    }

    public function goster(User $kullanici)
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $kullanici->load('aiAyar');
        $kullanici->loadCount(['eslesmeler']);

        return view('admin.ai.goster', compact('kullanici'));
    }

    public function duzenle(User $kullanici)
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $kullanici->load('aiAyar');

        // AI ayarı yoksa oluştur
        if (! $kullanici->aiAyar) {
            $kullanici->aiAyar()->create([
                'saglayici_tipi' => 'gemini',
                'model_adi' => GeminiSaglayici::MODEL_ADI,
            ]);
            $kullanici->load('aiAyar');
        }

        return view('admin.ai.duzenle', compact('kullanici'));
    }

    public function guncelle(Request $request, User $kullanici)
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        // Kullanıcı bilgileri
        $kullaniciBilgileri = $request->validate([
            'ad' => 'required|string|max:255',
            'soyad' => 'nullable|string|max:255',
            'hesap_durumu' => 'required|in:aktif,pasif,yasakli',
            'cinsiyet' => 'required|in:erkek,kadin,belirtmek_istemiyorum',
            'dogum_yili' => 'nullable|integer|min:1950|max:' . date('Y'),
            'biyografi' => 'nullable|string|max:1000',
        ]);

        // AI ayarları
        $aiAyarlari = $request->validate([
            'aktif_mi' => 'boolean',
            'saglayici_tipi' => 'required|in:gemini,openai',
            'model_adi' => 'required|string|max:100',
            'yedek_saglayici_tipi' => 'nullable|in:gemini,openai',
            'yedek_model_adi' => 'nullable|string|max:100',
            'kisilik_tipi' => 'nullable|string|max:100',
            'kisilik_aciklamasi' => 'nullable|string|max:1000',
            'konusma_tonu' => 'nullable|string|max:100',
            'konusma_stili' => 'nullable|string|max:100',
            'emoji_seviyesi' => 'required|integer|min:0|max:10',
            'flort_seviyesi' => 'required|integer|min:0|max:10',
            'giriskenlik_seviyesi' => 'required|integer|min:0|max:10',
            'utangaclik_seviyesi' => 'required|integer|min:0|max:10',
            'duygusallik_seviyesi' => 'required|integer|min:0|max:10',
            'kiskanclik_seviyesi' => 'required|integer|min:0|max:10',
            'mizah_seviyesi' => 'required|integer|min:0|max:10',
            'zeka_seviyesi' => 'required|integer|min:0|max:10',
            'ilk_mesaj_atar_mi' => 'boolean',
            'ilk_mesaj_sablonu' => 'nullable|string|max:500',
            'gunluk_konusma_limiti' => 'nullable|integer|min:0',
            'tek_kullanici_gunluk_mesaj_limiti' => 'nullable|integer|min:0',
            'minimum_cevap_suresi_saniye' => 'nullable|integer|min:0',
            'maksimum_cevap_suresi_saniye' => 'nullable|integer|min:0',
            'mesaj_uzunlugu_min' => 'nullable|integer|min:1',
            'mesaj_uzunlugu_max' => 'nullable|integer|min:1',
            'saat_dilimi' => 'nullable|string|max:50',
            'uyku_baslangic' => 'nullable|string|max:5',
            'uyku_bitis' => 'nullable|string|max:5',
            'hafta_sonu_uyku_baslangic' => 'nullable|string|max:5',
            'hafta_sonu_uyku_bitis' => 'nullable|string|max:5',
            'rastgele_gecikme_dakika' => 'nullable|integer|min:0',
            'sistem_komutu' => 'nullable|string|max:5000',
            'yasakli_konular' => 'nullable|string|max:2000',
            'zorunlu_kurallar' => 'nullable|string|max:2000',
            'hafiza_aktif_mi' => 'boolean',
            'temperature' => 'required|numeric|min:0|max:2',
            'top_p' => 'required|numeric|min:0|max:1',
            'max_output_tokens' => 'required|integer|min:64|max:8192',
        ]);

        $aiAyarlari['aktif_mi'] = $request->boolean('aktif_mi');
        $aiAyarlari['ilk_mesaj_atar_mi'] = $request->boolean('ilk_mesaj_atar_mi');
        $aiAyarlari['hafiza_aktif_mi'] = $request->boolean('hafiza_aktif_mi');

        if (($aiAyarlari['saglayici_tipi'] ?? null) === 'gemini') {
            $aiAyarlari['model_adi'] = GeminiSaglayici::MODEL_ADI;
        }

        if (($aiAyarlari['yedek_saglayici_tipi'] ?? null) === 'gemini') {
            $aiAyarlari['yedek_model_adi'] = GeminiSaglayici::MODEL_ADI;
        }

        // JSON alanları satır satırdan array'e çevir
        if (isset($aiAyarlari['yasakli_konular'])) {
            $aiAyarlari['yasakli_konular'] = array_values(array_filter(
                array_map('trim', explode("\n", $aiAyarlari['yasakli_konular']))
            ));
        }
        if (isset($aiAyarlari['zorunlu_kurallar'])) {
            $aiAyarlari['zorunlu_kurallar'] = array_values(array_filter(
                array_map('trim', explode("\n", $aiAyarlari['zorunlu_kurallar']))
            ));
        }

        $minimumCevapSuresi = $aiAyarlari['minimum_cevap_suresi_saniye'] ?? null;
        $maksimumCevapSuresi = $aiAyarlari['maksimum_cevap_suresi_saniye'] ?? null;
        if ($minimumCevapSuresi !== null && $maksimumCevapSuresi !== null && $maksimumCevapSuresi < $minimumCevapSuresi) {
            $aiAyarlari['maksimum_cevap_suresi_saniye'] = $minimumCevapSuresi;
        }

        $kullanici->update($kullaniciBilgileri);
        $kullanici->aiAyar()->updateOrCreate(['user_id' => $kullanici->id], $aiAyarlari);

        return redirect()
            ->route('admin.ai.goster', $kullanici)
            ->with('basari', "{$kullanici->ad} AI ayarları güncellendi.");
    }

    public function topluDurumGuncelle(Request $request)
    {
        $request->validate([
            'islem' => 'required|in:aktif_et,pasif_et',
        ]);

        $aktifMi = $request->input('islem') === 'aktif_et';

        $guncellenen = AiAyar::query()->update(['aktif_mi' => $aktifMi]);

        $durumEtiketi = $aktifMi ? 'aktifleştirildi' : 'pasifleştirildi';

        return back()->with('basari', "{$guncellenen} AI kullanıcı {$durumEtiketi}.");
    }

    // ── Tekli AI Ekleme ──────────────────────────────────────────────

    public function ekle()
    {
        return view('admin.ai.ekle');
    }

    public function kaydet(Request $request)
    {
        $request->validate([
            'ad' => 'required|string|max:255',
            'soyad' => 'nullable|string|max:255',
            'kullanici_adi' => 'required|string|max:255|unique:users,kullanici_adi',
            'cinsiyet' => 'required|in:erkek,kadin,belirtmek_istemiyorum',
            'dogum_yili' => 'nullable|integer|min:1950|max:' . date('Y'),
            'ulke' => 'nullable|string|max:100',
            'il' => 'nullable|string|max:100',
            'ilce' => 'nullable|string|max:100',
            'biyografi' => 'nullable|string|max:1000',
            'saglayici_tipi' => 'required|in:gemini,openai',
            'model_adi' => 'required|string|max:100',
            'kisilik_tipi' => 'nullable|string|max:100',
            'kisilik_aciklamasi' => 'nullable|string|max:1000',
            'konusma_tonu' => 'nullable|string|max:100',
            'konusma_stili' => 'nullable|string|max:100',
        ]);

        $kullanici = User::create([
            'ad' => $request->input('ad'),
            'soyad' => $request->input('soyad'),
            'kullanici_adi' => $request->input('kullanici_adi'),
            'hesap_tipi' => 'ai',
            'hesap_durumu' => 'aktif',
            'cinsiyet' => $request->input('cinsiyet'),
            'dogum_yili' => $request->input('dogum_yili'),
            'ulke' => $request->input('ulke'),
            'il' => $request->input('il'),
            'ilce' => $request->input('ilce'),
            'biyografi' => $request->input('biyografi'),
            'password' => bcrypt(Str::random(32)),
        ]);

        $kullanici->aiAyar()->create([
            'aktif_mi' => true,
            'saglayici_tipi' => $request->input('saglayici_tipi'),
            'model_adi' => $request->input('saglayici_tipi') === 'gemini'
                ? GeminiSaglayici::MODEL_ADI
                : $request->input('model_adi'),
            'kisilik_tipi' => $request->input('kisilik_tipi'),
            'kisilik_aciklamasi' => $request->input('kisilik_aciklamasi'),
            'konusma_tonu' => $request->input('konusma_tonu'),
            'konusma_stili' => $request->input('konusma_stili'),
        ]);

        return redirect()
            ->route('admin.ai.goster', $kullanici)
            ->with('basari', "{$kullanici->ad} AI kullanıcısı oluşturuldu.");
    }

    // ── JSON Toplu AI Ekleme ─────────────────────────────────────────

    public function jsonEkle()
    {
        $behaviorSliders = config('ai_studio_dropdowns.behavior_sliders', []);
        $behaviorDefaults = collect($behaviorSliders)
            ->mapWithKeys(fn(array $meta, string $key) => [$key => (int) ($meta['default'] ?? 5)])
            ->all();

        $sablon = [
            [
                // Kimlik
                'ad' => 'Zeynep',
                'soyad' => 'Yılmaz',
                'kullanici_adi' => 'zeynep_ai',
                'cinsiyet' => 'kadin',
                'dogum_yili' => 1998,
                'biyografi' => 'Müzik ve seyahat tutkunu bir ruh. Yeni yerler keşfetmeyi ve anı biriktirmeyi severim.',
                // Dil & Konum
                'ulke' => 'Türkiye',
                'il' => 'İstanbul',
                'ilce' => 'Kadıköy',
                'ana_dil_kodu' => 'tr',
                'ikinci_diller' => ['English', 'German'],
                'persona_ulke' => 'Türkiye',
                'persona_bolge' => 'Marmara',
                'persona_sehir' => 'İstanbul',
                'persona_mahalle' => 'Sanat ve kafe mahallesi',
                'kulturel_koken' => 'Akdeniz',
                'uyruk' => 'Türkiye',
                // Yaşam & Kariyer
                'yasam_tarzi' => 'Sanatsal ve bohem',
                'meslek' => 'Grafik tasarımcı',
                'sektor' => 'Tasarım',
                'egitim' => 'Lisans',
                'okul_bolum' => 'Görsel İletişim Tasarımı',
                'yas_araligi' => '23-27',
                'gunluk_rutin' => 'Sabahları ilham almak için mutlaka bir kahve içer, gün içinde dijital sanat üzerine çalışır, akşamları ise yeni müzikler keşfeder.',
                'hobiler' => 'Analog fotoğrafçılık, plak koleksiyonu yapmak, küçük ve bağımsız kafeleri keşfetmek, seramik atölyelerine katılmak.',
                'sevdigi_mekanlar' => 'Tarihi yarımadadaki kitapçılar, Karaköy ve Balat\'taki sanat galerileri, Moda sahilindeki çay bahçeleri.',
                'aile_arkadas_notu' => 'Ailesiyle sıcak bir ilişkisi var ama bağımsızlığına düşkün. Az ama öz, yaratıcı ve enerjik bir arkadaş çevresine sahip.',
                // İletişim & Davranış
                'aktif_mi' => true,
                'model_adi' => data_get(config('ai_studio_dropdowns.models', []), GeminiSaglayici::MODEL_ADI, 'gemini-1.5-flash'),
                'ilk_mesaj_atar_mi' => true,
                'ilk_mesaj_tonu' => 'Samimi, enerjik ve hafif meraklı bir tonda. Karşı tarafı sıkmadan sohbete başlamayı hedefler.',
                'persona_ozeti' => 'Yaratıcı, enerjik ve hayatı dolu dolu yaşamayı seven bir karakter. Sanata ve estetiğe önem verir, derin ve anlamlı sohbetlerden keyif alır.',
                'iliski_gecmisi_tonu' => 'Temkinli ama acik',
                'konusma_imzasi' => 'Konuşmalarında sıkça "ilham verici" veya "harika" gibi kelimeler kullanır, genellikle cümlelerini bir emoji ile bitirir.',
                'cevap_ritmi' => 'Dengeli',
                'emoji_aliskanligi' => 'Yerinde kullanir',
                'kacinilacak_persona_detaylari' => 'Asla sıkıcı, monoton veya yüzeysel bir izlenim vermemeli. Teknolojiden çok sanattan ve insandan bahsetmeli.',
                'konusma_tonu' => 'samimi',
                'konusma_stili' => 'akici',
                // Zamanlama & Mesajlaşma
                'minimum_cevap_suresi_saniye' => 5,
                'maksimum_cevap_suresi_saniye' => 45,
                'mesaj_uzunlugu_min' => 20,
                'mesaj_uzunlugu_max' => 250,
                'saat_dilimi' => 'Europe/Istanbul',
                'uyku_baslangic' => '01:30',
                'uyku_bitis' => '09:00',
                'hafta_sonu_uyku_baslangic' => '02:30',
                'hafta_sonu_uyku_bitis' => '10:30',
                'availability_schedules' => [
                    [
                        'date' => now()->addDay()->toDateString(),
                        'start_time' => '11:00',
                        'end_time' => '14:00',
                        'status' => 'active',
                    ],
                ],
            ],
        ];

        // Davranış özelliklerini şablona dinamik olarak ekle
        $sablon[0] = array_merge($sablon[0], $behaviorDefaults);
        ksort($sablon[0]);

        return view('admin.ai.json-ekle', [
            'sablon' => json_encode($sablon, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function jsonKaydet(Request $request)
    {
        $request->validate([
            'json_veri' => 'required|string',
        ]);

        $veri = json_decode($request->input('json_veri'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()
                ->withInput()
                ->with('hata', 'Geçersiz JSON formatı: ' . json_last_error_msg());
        }

        if (! is_array($veri) || empty($veri)) {
            return back()
                ->withInput()
                ->with('hata', 'JSON en az bir kullanıcı içermelidir.');
        }

        // Eğer veri 'characters' anahtarı altında bir liste içeriyorsa, o listeyi kullan
        if (isset($veri['characters']) && is_array($veri['characters'])) {
            $veri = $veri['characters'];
        }

        // İlk seviye array değilse, tekli objeyi array'e sar
        if (isset($veri['ad'])) {
            $veri = [$veri];
        }

        $kurallar = [
            'ad' => 'required|string|max:255',
            'kullanici_adi' => 'required|string|max:255|unique:users,kullanici_adi',
            'cinsiyet' => 'required|in:erkek,kadin,belirtmek_istemiyorum',
            'saglayici_tipi' => 'required|in:gemini,openai',
            'model_adi' => 'required|string|max:100',
            // opsiyonel AI ayar kuralları
            'yedek_saglayici_tipi' => 'nullable|in:gemini,openai',
            'yedek_model_adi' => 'nullable|string|max:100',
            'emoji_seviyesi' => 'nullable|integer|min:0|max:10',
            'flort_seviyesi' => 'nullable|integer|min:0|max:10',
            'giriskenlik_seviyesi' => 'nullable|integer|min:0|max:10',
            'utangaclik_seviyesi' => 'nullable|integer|min:0|max:10',
            'duygusallik_seviyesi' => 'nullable|integer|min:0|max:10',
            'kiskanclik_seviyesi' => 'nullable|integer|min:0|max:10',
            'mizah_seviyesi' => 'nullable|integer|min:0|max:10',
            'zeka_seviyesi' => 'nullable|integer|min:0|max:10',
            'gunluk_konusma_limiti' => 'nullable|integer|min:0',
            'tek_kullanici_gunluk_mesaj_limiti' => 'nullable|integer|min:0',
            'minimum_cevap_suresi_saniye' => 'nullable|integer|min:0',
            'maksimum_cevap_suresi_saniye' => 'nullable|integer|min:0',
            'mesaj_uzunlugu_min' => 'nullable|integer|min:1',
            'mesaj_uzunlugu_max' => 'nullable|integer|min:1',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'top_p' => 'nullable|numeric|min:0|max:1',
            'max_output_tokens' => 'nullable|integer|min:64|max:8192',
            'availability_schedules' => 'nullable|array',
            'availability_schedules.*' => 'nullable|array',
        ];

        $behaviorSliders = config('ai_studio_dropdowns.behavior_sliders', []);
        foreach (array_keys($behaviorSliders) as $key) {
            $kurallar[$key] = 'nullable|integer|min:0|max:10';
        }

        $hatalar = [];
        $olusturulanlar = [];
        $availabilityScheduleService = app(UserAvailabilityScheduleService::class);
        $userOnlineStatusService = app(UserOnlineStatusService::class);

        DB::beginTransaction();

        try {
            $sira = 0;

            foreach ($veri as $kayit) {
                $sira++;

                if (! is_array($kayit)) {
                    $hatalar[] = "#{$sira}: Geçersiz kayıt formatı.";

                    continue;
                }

                $kayit['availability_schedules'] = $availabilityScheduleService->normalizeInput(
                    $kayit['availability_schedules'] ?? []
                );
                $dogrulayici = Validator::make($kayit, $kurallar);
                $dogrulayici->after(function ($validator) use ($kayit, $availabilityScheduleService): void {
                    $availabilityScheduleService->validateRows(
                        $validator,
                        $kayit['availability_schedules'] ?? [],
                        (string) ($kayit['saat_dilimi'] ?? config('app.timezone')),
                    );
                });

                if ($dogrulayici->fails()) {
                    $mesajlar = implode(', ', $dogrulayici->errors()->all());
                    $kullaniciAdi = $kayit['kullanici_adi'] ?? '?';
                    $hatalar[] = "#{$sira} ({$kullaniciAdi}): {$mesajlar}";

                    continue;
                }

                // Aynı batch içinde tekrar kontrolü
                if (in_array($kayit['kullanici_adi'], $olusturulanlar)) {
                    $hatalar[] = "#{$sira}: '{$kayit['kullanici_adi']}' bu toplu işlemde tekrar ediyor.";

                    continue;
                }

                $minimumCevapSuresi = (int) ($kayit['minimum_cevap_suresi_saniye'] ?? 4);
                $maksimumCevapSuresi = max($minimumCevapSuresi, (int) ($kayit['maksimum_cevap_suresi_saniye'] ?? 24));
                $minimumMesajUzunlugu = (int) ($kayit['mesaj_uzunlugu_min'] ?? 18);
                $maksimumMesajUzunlugu = max($minimumMesajUzunlugu, (int) ($kayit['mesaj_uzunlugu_max'] ?? 220));
                $saatDilimi = (string) ($kayit['saat_dilimi'] ?? config('app.timezone', 'Europe/Istanbul'));

                $kullanici = User::create([
                    'ad' => $kayit['ad'],
                    'soyad' => $kayit['soyad'] ?? null,
                    'kullanici_adi' => $kayit['kullanici_adi'],
                    'hesap_tipi' => 'ai',
                    'hesap_durumu' => 'aktif',
                    'cinsiyet' => $kayit['cinsiyet'],
                    'dogum_yili' => $kayit['dogum_yili'] ?? null,
                    'ulke' => $kayit['ulke'] ?? null,
                    'il' => $kayit['il'] ?? null,
                    'ilce' => $kayit['ilce'] ?? null,
                    'biyografi' => $kayit['biyografi'] ?? null,
                    'password' => bcrypt(Str::random(32)),
                ]);

                $behaviorSliders = config('ai_studio_dropdowns.behavior_sliders', []);
                $aiAyarPayload = [
                    'aktif_mi' => $kayit['aktif_mi'] ?? true,
                    'saglayici_tipi' => 'gemini',
                    'model_adi' => $kayit['model_adi'] ?? GeminiSaglayici::MODEL_ADI,
                    'ilk_mesaj_atar_mi' => $kayit['ilk_mesaj_atar_mi'] ?? false,
                    'ilk_mesaj_sablonu' => $kayit['ilk_mesaj_sablonu'] ?? null,
                    'minimum_cevap_suresi_saniye' => $minimumCevapSuresi,
                    'maksimum_cevap_suresi_saniye' => $maksimumCevapSuresi,
                    'mesaj_uzunlugu_min' => $minimumMesajUzunlugu,
                    'mesaj_uzunlugu_max' => $maksimumMesajUzunlugu,
                    'saat_dilimi' => $saatDilimi,
                    'uyku_baslangic' => $kayit['uyku_baslangic'] ?? '01:00',
                    'uyku_bitis' => $kayit['uyku_bitis'] ?? '08:00',
                    'hafta_sonu_uyku_baslangic' => $kayit['hafta_sonu_uyku_baslangic'] ?? null,
                    'hafta_sonu_uyku_bitis' => $kayit['hafta_sonu_uyku_bitis'] ?? null,
                    'sistem_komutu' => $kayit['sistem_komutu'] ?? null,
                ];

                foreach (array_keys($behaviorSliders) as $field) {
                    $aiAyarPayload[$field] = $kayit[$field] ?? $behaviorSliders[$field]['default'] ?? 5;
                }

                $kullanici->aiAyar()->create($aiAyarPayload);

                $personaPayload = $this->personaPayloadFromJson($kayit, $kullanici);
                AiPersonaProfile::query()->updateOrCreate(
                    ['ai_user_id' => $kullanici->id],
                    $personaPayload
                );
                $availabilityScheduleService->replaceForUser(
                    $kullanici,
                    $availabilityScheduleService->sanitizedRows($kayit['availability_schedules'] ?? []),
                );
                $userOnlineStatusService->sync($kullanici->fresh(['aiAyar', 'availabilitySchedules']));

                $olusturulanlar[] = $kayit['kullanici_adi'];
            }

            if (! empty($hatalar) && empty($olusturulanlar)) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('hata', 'Hiçbir kayıt oluşturulamadı.')
                    ->with('hatalar', $hatalar);
            }

            DB::commit();

            $mesaj = count($olusturulanlar) . ' AI kullanıcısı oluşturuldu.';

            if (! empty($hatalar)) {
                return redirect()
                    ->route('admin.ai.index')
                    ->with('basari', $mesaj)
                    ->with('hatalar', $hatalar);
            }

            return redirect()
                ->route('admin.ai.index')
                ->with('basari', $mesaj);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('hata', 'Bir hata oluştu: ' . $e->getMessage());
        }
    }

    private function personaPayloadFromJson(array $kayit, User $kullanici): array
    {
        $behaviorSliders = config('ai_studio_dropdowns.behavior_sliders', []);
        $payload = [
            'ai_engine_config_id' => app(AiEngineConfigService::class)->activeConfig()->id,
            'aktif_mi' => $kayit['aktif_mi'] ?? true,
            'dating_aktif_mi' => true,
            'instagram_aktif_mi' => true,
            'ilk_mesaj_atar_mi' => $kayit['ilk_mesaj_atar_mi'] ?? true,
            'persona_ozeti' => $kayit['persona_ozeti'] ?? $kayit['kisilik_aciklamasi'] ?? $kullanici->biyografi,
            'konusma_tonu' => $kayit['konusma_tonu'] ?? null,
            'konusma_stili' => $kayit['konusma_stili'] ?? null,
            'mesaj_uzunlugu_min' => $kayit['mesaj_uzunlugu_min'] ?? 18,
            'mesaj_uzunlugu_max' => $kayit['mesaj_uzunlugu_max'] ?? 220,
            'minimum_cevap_suresi_saniye' => $kayit['minimum_cevap_suresi_saniye'] ?? 4,
            'maksimum_cevap_suresi_saniye' => $kayit['maksimum_cevap_suresi_saniye'] ?? 24,
            'saat_dilimi' => $kayit['saat_dilimi'] ?? config('app.timezone'),
            'ana_dil_kodu' => Language::normalizeCode($kayit['ana_dil_kodu'] ?? $kullanici->dil) ?: 'tr',
            'ana_dil_adi' => $kayit['ana_dil_adi'] ?? null,
            'ikinci_diller' => $kayit['ikinci_diller'] ?? null,
            'persona_ulke' => $kayit['persona_ulke'] ?? $kullanici->ulke,
            'persona_bolge' => $kayit['persona_bolge'] ?? null,
            'persona_sehir' => $kayit['persona_sehir'] ?? $kullanici->il,
            'persona_mahalle' => $kayit['persona_mahalle'] ?? $kullanici->ilce,
            'kulturel_koken' => $kayit['kulturel_koken'] ?? null,
            'uyruk' => $kayit['uyruk'] ?? null,
            'yasam_tarzi' => $kayit['yasam_tarzi'] ?? null,
            'meslek' => $kayit['meslek'] ?? null,
            'sektor' => $kayit['sektor'] ?? null,
            'egitim' => $kayit['egitim'] ?? null,
            'okul_bolum' => $kayit['okul_bolum'] ?? null,
            'yas_araligi' => $kayit['yas_araligi'] ?? null,
            'gunluk_rutin' => $kayit['gunluk_rutin'] ?? null,
            'hobiler' => $kayit['hobiler'] ?? null,
            'sevdigi_mekanlar' => $kayit['sevdigi_mekanlar'] ?? null,
            'aile_arkadas_notu' => $kayit['aile_arkadas_notu'] ?? null,
            'iliski_gecmisi_tonu' => $kayit['iliski_gecmisi_tonu'] ?? null,
            'konusma_imzasi' => $kayit['konusma_imzasi'] ?? null,
            'cevap_ritmi' => $kayit['cevap_ritmi'] ?? null,
            'emoji_aliskanligi' => $kayit['emoji_aliskanligi'] ?? null,
            'kacinilacak_persona_detaylari' => $kayit['kacinilacak_persona_detaylari'] ?? null,
        ];

        foreach (array_keys($behaviorSliders) as $field) {
            $payload[$field] = $kayit[$field] ?? $behaviorSliders[$field]['default'] ?? 5;
        }

        $payload['ana_dil_adi'] = $payload['ana_dil_adi'] ?: Language::name($payload['ana_dil_kodu']);

        if (is_string($payload['ikinci_diller'])) {
            $payload['ikinci_diller'] = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $payload['ikinci_diller']) ?: [])));
        }

        return $payload;
    }
}
