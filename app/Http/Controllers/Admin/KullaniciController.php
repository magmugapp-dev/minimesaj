<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sikayet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KullaniciController extends Controller
{
    public function index(Request $request)
    {
        $sorgu = User::where('hesap_tipi', 'user');

        // Arama
        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('ad', 'like', "%{$arama}%")
                    ->orWhere('soyad', 'like', "%{$arama}%")
                    ->orWhere('kullanici_adi', 'like', "%{$arama}%")
                    ->orWhere('email', 'like', "%{$arama}%");
            });
        }

        // Hesap tipi filtresi
        if ($hesapTipi = $request->input('hesap_tipi')) {
            $sorgu->where('hesap_tipi', $hesapTipi);
        }

        // Durum filtresi
        if ($durum = $request->input('durum')) {
            $sorgu->where('hesap_durumu', $durum);
        }

        // Premium filtresi
        if ($request->input('premium') === '1') {
            $sorgu->where('premium_aktif_mi', true);
        }

        // Sıralama
        $siralanacakAlan = $request->input('sirala', 'created_at');
        $siralanacakYon = $request->input('yon', 'desc');

        $izinliAlanlar = ['ad', 'kullanici_adi', 'email', 'hesap_tipi', 'hesap_durumu', 'created_at', 'mevcut_puan'];
        if (! in_array($siralanacakAlan, $izinliAlanlar)) {
            $siralanacakAlan = 'created_at';
        }

        $sorgu->orderBy($siralanacakAlan, $siralanacakYon === 'asc' ? 'asc' : 'desc');

        $kullanicilar = $sorgu->paginate(25)->withQueryString();

        return view('admin.kullanicilar.index', compact('kullanicilar'));
    }

    public function goster(User $kullanici)
    {
        $kullanici->loadCount([
            'eslesmeler',
            'sikayetler',
            'odemeler',
            'puanHareketleri',
        ]);

        $kullanici->load([
            'fotograflar',
            'aiCharacter',
        ]);

        $sonOdemeler = $kullanici->odemeler()->latest()->take(5)->get();
        $hakkindaSikayetler = Sikayet::query()
            ->where('hedef_tipi', Sikayet::HEDEF_TIPI_USER)
            ->where('hedef_id', $kullanici->id)
            ->with('sikayetEden:id,ad,soyad,kullanici_adi')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.kullanicilar.goster', compact(
            'kullanici',
            'sonOdemeler',
            'hakkindaSikayetler',
        ));
    }

    public function duzenle(User $kullanici)
    {
        return view('admin.kullanicilar.duzenle', compact('kullanici'));
    }

    public function guncelle(Request $request, User $kullanici)
    {
        $dogrulanan = $request->validate([
            'ad' => 'required|string|max:255',
            'soyad' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $kullanici->id,
            'hesap_durumu' => 'required|in:aktif,pasif,yasakli',
            'hesap_tipi' => 'required|in:user,ai',
            'cinsiyet' => 'required|in:erkek,kadin,belirtmek_istemiyorum',
            'dogum_yili' => 'nullable|integer|min:1950|max:' . (date('Y') - 18),
            'ulke' => 'nullable|string|max:100',
            'il' => 'nullable|string|max:100',
            'ilce' => 'nullable|string|max:100',
            'biyografi' => 'nullable|string|max:500',
            'mevcut_puan' => 'required|integer|min:0',
            'gunluk_ucretsiz_hak' => 'required|integer|min:0',
            'premium_aktif_mi' => 'boolean',
        ]);

        $dogrulanan['premium_aktif_mi'] = $request->boolean('premium_aktif_mi');

        $kullanici->update($dogrulanan);

        return redirect()
            ->route('admin.kullanicilar.goster', $kullanici)
            ->with('basari', "{$kullanici->kullanici_adi} başarıyla güncellendi.");
    }

    public function durumGuncelle(Request $request, User $kullanici)
    {
        $request->validate([
            'hesap_durumu' => 'required|in:aktif,pasif,yasakli',
        ]);

        // Kendini yasaklamasını engelle
        if ($kullanici->id === Auth::id()) {
            return back()->with('hata', 'Kendi hesabınızın durumunu değiştiremezsiniz.');
        }

        $kullanici->update(['hesap_durumu' => $request->input('hesap_durumu')]);

        $durumEtiketi = match ($request->input('hesap_durumu')) {
            'aktif' => 'aktifleştirildi',
            'pasif' => 'pasifleştirildi',
            'yasakli' => 'yasaklandı',
        };

        return back()->with('basari', "{$kullanici->kullanici_adi} hesabı {$durumEtiketi}.");
    }
}
