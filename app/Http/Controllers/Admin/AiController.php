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
        $sablon = [
            [
                'ad' => 'Zeynep',
                'soyad' => 'Yılmaz',
                'kullanici_adi' => 'zeynep_ai',
                'cinsiyet' => 'kadin',
                'dogum_yili' => 1998,
                'ulke' => 'Türkiye',
                'il' => 'İstanbul',
                'ilce' => 'Kadıköy',
                'biyografi' => 'Müzik ve seyahat tutkunu',
                'ana_dil_kodu' => 'tr',
                'ana_dil_adi' => 'Turkish',
                'ikinci_diller' => ['English'],
                'persona_ulke' => 'Türkiye',
                'persona_bolge' => 'Marmara',
                'persona_sehir' => 'İstanbul',
                'meslek' => 'Grafik tasarımcı',
                'sektor' => 'Tasarım',
                'egitim' => 'Üniversite',
                'yas_araligi' => '24-28',
                'gunluk_rutin' => 'Sabah kahve, gün içinde tasarım işleri, akşam yürüyüş.',
                'hobiler' => 'Müzik, fotoğraf, küçük kafeler',
                'konusma_imzasi' => 'Kısa, doğal ve hafif şakacı yazar.',
                // AI Ayarları
                'aktif_mi' => true,
                'saglayici_tipi' => 'gemini',
                'model_adi' => GeminiSaglayici::MODEL_ADI,
                'yedek_saglayici_tipi' => null,
                'yedek_model_adi' => null,
                'kisilik_tipi' => 'eglenceli',
                'kisilik_aciklamasi' => 'Neşeli ve enerjik bir karakter',
                'konusma_tonu' => 'samimi',
                'konusma_stili' => 'gunluk',
                // Seviye Ayarları (0-10)
                'emoji_seviyesi' => 5,
                'flort_seviyesi' => 6,
                'giriskenlik_seviyesi' => 7,
                'utangaclik_seviyesi' => 3,
                'duygusallik_seviyesi' => 5,
                'kiskanclik_seviyesi' => 2,
                'mizah_seviyesi' => 7,
                'zeka_seviyesi' => 6,
                // Mesajlaşma
                'ilk_mesaj_atar_mi' => true,
                'ilk_mesaj_sablonu' => 'Merhaba! Nasılsın? 😊',
                'gunluk_konusma_limiti' => 100,
                'tek_kullanici_gunluk_mesaj_limiti' => 50,
                'minimum_cevap_suresi_saniye' => 3,
                'maksimum_cevap_suresi_saniye' => 30,
                'ortalama_mesaj_uzunlugu' => 80,
                'mesaj_uzunlugu_min' => 10,
                'mesaj_uzunlugu_max' => 300,
                'sesli_mesaj_gonderebilir_mi' => false,
                'foto_gonderebilir_mi' => false,
                // Zamanlama
                'saat_dilimi' => 'Europe/Istanbul',
                'uyku_baslangic' => '01:00',
                'uyku_bitis' => '08:00',
                'hafta_sonu_uyku_baslangic' => '02:00',
                'hafta_sonu_uyku_bitis' => '10:00',
                'rastgele_gecikme_dakika' => 0,
                'availability_schedules' => [
                    [
                        'date' => now()->addDay()->toDateString(),
                        'start_time' => '10:00',
                        'end_time' => '13:00',
                        'status' => 'active',
                    ],
                    [
                        'date' => now()->addDay()->toDateString(),
                        'start_time' => '22:00',
                        'end_time' => '23:30',
                        'status' => 'passive',
                    ],
                ],
                // Sistem
                'sistem_komutu' => 'Sen bir arkadaş canlısı genç kadınsın.',
                'yasakli_konular' => ['politika', 'din'],
                'zorunlu_kurallar' => ['Türkçe konuş', 'Kibar ol'],
                // Hafıza
                'hafiza_aktif_mi' => true,
                'hafiza_seviyesi' => 'orta',
                'kullaniciyi_hatirlar_mi' => true,
                'iliski_seviyesi_takibi_aktif_mi' => true,
                'puanlama_etiketi' => 'A',
                // Model Parametreleri
                'temperature' => 0.8,
                'top_p' => 0.9,
                'max_output_tokens' => 1024,
            ],
        ];

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

        $hatalar = [];
        $olusturulanlar = [];
        $availabilityScheduleService = app(UserAvailabilityScheduleService::class);
        $userOnlineStatusService = app(UserOnlineStatusService::class);

        DB::beginTransaction();

        try {
            foreach ($veri as $index => $kayit) {
                $sira = $index + 1;

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

                $kullanici->aiAyar()->create([
                    'aktif_mi' => $kayit['aktif_mi'] ?? true,
                    'saglayici_tipi' => $kayit['saglayici_tipi'],
                    'model_adi' => $kayit['saglayici_tipi'] === 'gemini'
                        ? GeminiSaglayici::MODEL_ADI
                        : $kayit['model_adi'],
                    'yedek_saglayici_tipi' => $kayit['yedek_saglayici_tipi'] ?? null,
                    'yedek_model_adi' => ($kayit['yedek_saglayici_tipi'] ?? null) === 'gemini'
                        ? GeminiSaglayici::MODEL_ADI
                        : ($kayit['yedek_model_adi'] ?? null),
                    'kisilik_tipi' => $kayit['kisilik_tipi'] ?? null,
                    'kisilik_aciklamasi' => $kayit['kisilik_aciklamasi'] ?? null,
                    'konusma_tonu' => $kayit['konusma_tonu'] ?? null,
                    'konusma_stili' => $kayit['konusma_stili'] ?? null,
                    'emoji_seviyesi' => $kayit['emoji_seviyesi'] ?? 5,
                    'flort_seviyesi' => $kayit['flort_seviyesi'] ?? 5,
                    'giriskenlik_seviyesi' => $kayit['giriskenlik_seviyesi'] ?? 5,
                    'utangaclik_seviyesi' => $kayit['utangaclik_seviyesi'] ?? 5,
                    'duygusallik_seviyesi' => $kayit['duygusallik_seviyesi'] ?? 5,
                    'kiskanclik_seviyesi' => $kayit['kiskanclik_seviyesi'] ?? 3,
                    'mizah_seviyesi' => $kayit['mizah_seviyesi'] ?? 5,
                    'zeka_seviyesi' => $kayit['zeka_seviyesi'] ?? 5,
                    'ilk_mesaj_atar_mi' => $kayit['ilk_mesaj_atar_mi'] ?? false,
                    'ilk_mesaj_sablonu' => $kayit['ilk_mesaj_sablonu'] ?? null,
                    'gunluk_konusma_limiti' => $kayit['gunluk_konusma_limiti'] ?? null,
                    'tek_kullanici_gunluk_mesaj_limiti' => $kayit['tek_kullanici_gunluk_mesaj_limiti'] ?? null,
                    'minimum_cevap_suresi_saniye' => $kayit['minimum_cevap_suresi_saniye'] ?? null,
                    'maksimum_cevap_suresi_saniye' => $kayit['maksimum_cevap_suresi_saniye'] ?? null,
                    'ortalama_mesaj_uzunlugu' => $kayit['ortalama_mesaj_uzunlugu'] ?? null,
                    'mesaj_uzunlugu_min' => $kayit['mesaj_uzunlugu_min'] ?? null,
                    'mesaj_uzunlugu_max' => $kayit['mesaj_uzunlugu_max'] ?? null,
                    'sesli_mesaj_gonderebilir_mi' => $kayit['sesli_mesaj_gonderebilir_mi'] ?? false,
                    'foto_gonderebilir_mi' => $kayit['foto_gonderebilir_mi'] ?? false,
                    'saat_dilimi' => $kayit['saat_dilimi'] ?? null,
                    'uyku_baslangic' => $kayit['uyku_baslangic'] ?? null,
                    'uyku_bitis' => $kayit['uyku_bitis'] ?? null,
                    'hafta_sonu_uyku_baslangic' => $kayit['hafta_sonu_uyku_baslangic'] ?? null,
                    'hafta_sonu_uyku_bitis' => $kayit['hafta_sonu_uyku_bitis'] ?? null,
                    'rastgele_gecikme_dakika' => $kayit['rastgele_gecikme_dakika'] ?? null,
                    'sistem_komutu' => $kayit['sistem_komutu'] ?? null,
                    'yasakli_konular' => $kayit['yasakli_konular'] ?? null,
                    'zorunlu_kurallar' => $kayit['zorunlu_kurallar'] ?? null,
                    'hafiza_aktif_mi' => $kayit['hafiza_aktif_mi'] ?? false,
                    'hafiza_seviyesi' => $kayit['hafiza_seviyesi'] ?? null,
                    'kullaniciyi_hatirlar_mi' => $kayit['kullaniciyi_hatirlar_mi'] ?? false,
                    'iliski_seviyesi_takibi_aktif_mi' => $kayit['iliski_seviyesi_takibi_aktif_mi'] ?? false,
                    'puanlama_etiketi' => $kayit['puanlama_etiketi'] ?? null,
                    'temperature' => $kayit['temperature'] ?? 0.8,
                    'top_p' => $kayit['top_p'] ?? 0.9,
                    'max_output_tokens' => $kayit['max_output_tokens'] ?? 1024,
                ]);

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
        $payload = [
            'ai_engine_config_id' => app(AiEngineConfigService::class)->activeConfig()->id,
            'aktif_mi' => $kayit['aktif_mi'] ?? true,
            'dating_aktif_mi' => true,
            'instagram_aktif_mi' => true,
            'ilk_mesaj_atar_mi' => $kayit['ilk_mesaj_atar_mi'] ?? true,
            'persona_ozeti' => $kayit['persona_ozeti'] ?? $kayit['kisilik_aciklamasi'] ?? $kullanici->biyografi,
            'konusma_tonu' => $kayit['konusma_tonu'] ?? null,
            'konusma_stili' => $kayit['konusma_stili'] ?? null,
            'mizah_seviyesi' => $kayit['mizah_seviyesi'] ?? 5,
            'flort_seviyesi' => $kayit['flort_seviyesi'] ?? 4,
            'emoji_seviyesi' => $kayit['emoji_seviyesi'] ?? 3,
            'giriskenlik_seviyesi' => $kayit['giriskenlik_seviyesi'] ?? 5,
            'utangaclik_seviyesi' => $kayit['utangaclik_seviyesi'] ?? 3,
            'duygusallik_seviyesi' => $kayit['duygusallik_seviyesi'] ?? 5,
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
            'argo_seviyesi' => $kayit['argo_seviyesi'] ?? 2,
            'cevap_ritmi' => $kayit['cevap_ritmi'] ?? null,
            'emoji_aliskanligi' => $kayit['emoji_aliskanligi'] ?? null,
            'kacinilacak_persona_detaylari' => $kayit['kacinilacak_persona_detaylari'] ?? null,
        ];

        $payload['ana_dil_adi'] = $payload['ana_dil_adi'] ?: Language::name($payload['ana_dil_kodu']);

        if (is_string($payload['ikinci_diller'])) {
            $payload['ikinci_diller'] = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $payload['ikinci_diller']) ?: [])));
        }

        return $payload;
    }
}
