<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAyar;
use App\Models\InstagramHesap;
use App\Models\User;
use App\Services\YapayZeka\GeminiSaglayici;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InfluencerController extends Controller
{
    public function index(Request $request)
    {
        $sorgu = User::where('hesap_tipi', 'ai')
            ->whereHas('instagramHesaplari')
            ->with(['aiAyar', 'instagramHesaplari']);

        // Arama
        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('ad', 'like', "%{$arama}%")
                    ->orWhere('soyad', 'like', "%{$arama}%")
                    ->orWhere('kullanici_adi', 'like', "%{$arama}%")
                    ->orWhereHas('instagramHesaplari', function ($q2) use ($arama) {
                        $q2->where('instagram_kullanici_adi', 'like', "%{$arama}%");
                    });
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

        $sorgu->orderBy($request->input('sirala', 'created_at'), $request->input('yon', 'desc') === 'asc' ? 'asc' : 'desc');

        $influencerlar = $sorgu->paginate(25)->withQueryString();

        // İstatistikler
        $toplamInfluencer = User::where('hesap_tipi', 'ai')->whereHas('instagramHesaplari')->count();
        $istatistikler = [
            'toplam' => $toplamInfluencer,
            'aktif' => User::where('hesap_tipi', 'ai')
                ->where('hesap_durumu', 'aktif')
                ->whereHas('instagramHesaplari')
                ->whereHas('aiAyar', fn($q) => $q->where('aktif_mi', true))
                ->count(),
            'bagli' => InstagramHesap::whereHas('user', fn($q) => $q->where('hesap_tipi', 'ai'))
                ->where('aktif_mi', true)
                ->count(),
            'toplam_hesap' => InstagramHesap::whereHas('user', fn($q) => $q->where('hesap_tipi', 'ai'))
                ->count(),
        ];

        return view('admin.influencer.index', compact('influencerlar', 'istatistikler'));
    }

    public function ekle()
    {
        return view('admin.influencer.ekle');
    }

    public function kaydet(Request $request)
    {
        $request->validate([
            'ad' => 'required|string|max:255',
            'soyad' => 'nullable|string|max:255',
            'kullanici_adi' => 'required|string|max:255|unique:users,kullanici_adi',
            'sifre' => 'required|string|min:6|max:255',
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
            'instagram_kullanici_adi' => 'required|string|max:255',
            'instagram_profil_id' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
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
                'password' => Hash::make($request->input('sifre')),
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

            $kullanici->instagramHesaplari()->create([
                'instagram_kullanici_adi' => $request->input('instagram_kullanici_adi'),
                'instagram_profil_id' => $request->input('instagram_profil_id'),
                'otomatik_cevap_aktif_mi' => true,
                'aktif_mi' => true,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.influencer.goster', $kullanici)
                ->with('basari', "{$kullanici->ad} AI Influencer oluşturuldu.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('hata', 'Bir hata oluştu: ' . $e->getMessage());
        }
    }

    public function goster(User $kullanici)
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $kullanici->load(['aiAyar', 'instagramHesaplari']);
        $kullanici->loadCount(['eslesmeler']);

        // Instagram istatistikleri
        $instagramIstatistikleri = [];
        foreach ($kullanici->instagramHesaplari as $hesap) {
            $instagramIstatistikleri[$hesap->id] = [
                'kisi_sayisi' => $hesap->kisiler()->count(),
                'mesaj_sayisi' => $hesap->mesajlar()->count(),
                'gorev_sayisi' => $hesap->aiGorevleri()->count(),
            ];
        }

        return view('admin.influencer.goster', compact('kullanici', 'instagramIstatistikleri'));
    }

    public function duzenle(User $kullanici)
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $kullanici->load(['aiAyar', 'instagramHesaplari']);

        if (! $kullanici->aiAyar) {
            $kullanici->aiAyar()->create([
                'saglayici_tipi' => 'gemini',
                'model_adi' => GeminiSaglayici::MODEL_ADI,
            ]);
            $kullanici->load('aiAyar');
        }

        return view('admin.influencer.duzenle', compact('kullanici'));
    }

    public function guncelle(Request $request, User $kullanici)
    {
        abort_unless($kullanici->hesap_tipi === 'ai', 404);

        $kullaniciBilgileri = $request->validate([
            'ad' => 'required|string|max:255',
            'soyad' => 'nullable|string|max:255',
            'hesap_durumu' => 'required|in:aktif,pasif,yasakli',
            'cinsiyet' => 'required|in:erkek,kadin,belirtmek_istemiyorum',
            'dogum_yili' => 'nullable|integer|min:1950|max:' . date('Y'),
            'biyografi' => 'nullable|string|max:1000',
        ]);

        // Şifre güncelleme (opsiyonel)
        if ($request->filled('sifre')) {
            $request->validate(['sifre' => 'string|min:6|max:255']);
            $kullaniciBilgileri['password'] = Hash::make($request->input('sifre'));
        }

        $aiAyarlari = $request->validate([
            'aktif_mi' => 'boolean',
            'saglayici_tipi' => 'required|in:gemini,openai',
            'model_adi' => 'required|string|max:100',
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
            'temperature' => 'required|numeric|min:0|max:2',
            'top_p' => 'required|numeric|min:0|max:1',
            'max_output_tokens' => 'required|integer|min:64|max:8192',
        ]);

        $aiAyarlari['aktif_mi'] = $request->boolean('aktif_mi');
        $aiAyarlari['ilk_mesaj_atar_mi'] = $request->boolean('ilk_mesaj_atar_mi');

        if (($aiAyarlari['saglayici_tipi'] ?? null) === 'gemini') {
            $aiAyarlari['model_adi'] = GeminiSaglayici::MODEL_ADI;
        }

        // Instagram hesap bilgileri
        $instagramBilgileri = $request->validate([
            'instagram_kullanici_adi' => 'nullable|string|max:255',
            'instagram_profil_id' => 'nullable|string|max:255',
            'otomatik_cevap_aktif_mi' => 'boolean',
            'yarim_otomatik_mod_aktif_mi' => 'boolean',
            'instagram_aktif_mi' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            $kullanici->update($kullaniciBilgileri);
            $kullanici->aiAyar()->updateOrCreate(['user_id' => $kullanici->id], $aiAyarlari);

            // İlk Instagram hesabını güncelle
            $instagramHesap = $kullanici->instagramHesaplari()->first();
            if ($instagramHesap) {
                $instagramHesap->update([
                    'instagram_kullanici_adi' => $instagramBilgileri['instagram_kullanici_adi'] ?? $instagramHesap->instagram_kullanici_adi,
                    'instagram_profil_id' => $instagramBilgileri['instagram_profil_id'] ?? $instagramHesap->instagram_profil_id,
                    'otomatik_cevap_aktif_mi' => $request->boolean('otomatik_cevap_aktif_mi'),
                    'yarim_otomatik_mod_aktif_mi' => $request->boolean('yarim_otomatik_mod_aktif_mi'),
                    'aktif_mi' => $request->boolean('instagram_aktif_mi'),
                ]);
            }

            DB::commit();

            return redirect()
                ->route('admin.influencer.goster', $kullanici)
                ->with('basari', "{$kullanici->ad} AI Influencer güncellendi.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('hata', 'Bir hata oluştu: ' . $e->getMessage());
        }
    }
}
