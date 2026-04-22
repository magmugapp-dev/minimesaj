<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstagramAiGorevi;
use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use App\Models\InstagramMesaj;
use App\Models\AiHafiza;
use App\Services\Admin\AiHafizaPanelServisi;
use Illuminate\Http\Request;

class InstagramController extends Controller
{
    public function index(Request $request)
    {
        $sorgu = InstagramHesap::with('user');

        // Arama
        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('instagram_kullanici_adi', 'like', "%{$arama}%")
                    ->orWhereHas('user', function ($q2) use ($arama) {
                        $q2->where('ad', 'like', "%{$arama}%")
                            ->orWhere('kullanici_adi', 'like', "%{$arama}%")
                            ->orWhere('email', 'like', "%{$arama}%");
                    });
            });
        }

        // Durum filtresi
        if ($durum = $request->input('durum')) {
            if ($durum === 'bagli') {
                $sorgu->where('aktif_mi', true);
            } elseif ($durum === 'kopuk') {
                $sorgu->where('aktif_mi', false);
            }
        }

        // Oto-yanıt filtresi
        if ($otoYanit = $request->input('oto_yanit')) {
            if ($otoYanit === 'aktif') {
                $sorgu->where('otomatik_cevap_aktif_mi', true);
            } elseif ($otoYanit === 'pasif') {
                $sorgu->where('otomatik_cevap_aktif_mi', false);
            }
        }

        // İstatistikler
        $istatistikler = [
            'toplam_hesap'   => InstagramHesap::count(),
            'bagli'          => InstagramHesap::where('aktif_mi', true)->count(),
            'kopuk'          => InstagramHesap::where('aktif_mi', false)->count(),
            'oto_yanit'      => InstagramHesap::where('otomatik_cevap_aktif_mi', true)->count(),
            'yari_oto'       => InstagramHesap::where('yarim_otomatik_mod_aktif_mi', true)->count(),
            'toplam_kisi'    => InstagramKisi::count(),
            'toplam_mesaj'   => InstagramMesaj::count(),
            'ai_gorev'       => InstagramAiGorevi::count(),
        ];

        $hesaplar = $sorgu->withCount(['kisiler', 'mesajlar', 'aiGorevleri'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.instagram.index', compact('hesaplar', 'istatistikler'));
    }

    public function goster(InstagramHesap $instagramHesap)
    {
        $instagramHesap->load('user');
        $instagramHesap->loadCount(['kisiler', 'mesajlar', 'aiGorevleri']);

        $sonKisiler = $instagramHesap->kisiler()
            ->orderByDesc('son_mesaj_tarihi')
            ->take(10)
            ->get();

        $mesajIstatistikleri = [
            'toplam'      => $instagramHesap->mesajlar()->count(),
            'gelen'       => $instagramHesap->mesajlar()->where('gonderen_tipi', 'karsi_taraf')->count(),
            'giden'       => $instagramHesap->mesajlar()->where('gonderen_tipi', 'biz')->count(),
            'ai'          => $instagramHesap->mesajlar()->where('gonderen_tipi', 'ai')->count(),
            'bugun'       => $instagramHesap->mesajlar()->whereDate('created_at', today())->count(),
        ];

        $aiGorevIstatistikleri = [
            'toplam'      => $instagramHesap->aiGorevleri()->count(),
            'bekliyor'    => $instagramHesap->aiGorevleri()->where('durum', 'bekliyor')->count(),
            'isleniyor'   => $instagramHesap->aiGorevleri()
                ->whereIn('durum', ['isleniyor', 'istek_gonderildi', 'yanit_akiyor', 'yeniden_denecek'])
                ->count(),
            'tamamlandi'  => $instagramHesap->aiGorevleri()->where('durum', 'tamamlandi')->count(),
            'basarisiz'   => $instagramHesap->aiGorevleri()->where('durum', 'basarisiz')->count(),
        ];

        return view('admin.instagram.goster', compact(
            'instagramHesap',
            'sonKisiler',
            'mesajIstatistikleri',
            'aiGorevIstatistikleri',
        ));
    }

    public function kisiler(Request $request, InstagramHesap $instagramHesap)
    {
        $sorgu = $instagramHesap->kisiler();

        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('instagram_kullanici_adi', 'like', "%{$arama}%")
                    ->orWhere('gorunen_ad', 'like', "%{$arama}%")
                    ->orWhere('notlar', 'like', "%{$arama}%");
            });
        }

        $kisiler = $sorgu->withCount('mesajlar')
            ->orderByDesc('son_mesaj_tarihi')
            ->paginate(25)
            ->withQueryString();

        return view('admin.instagram.kisiler', compact('instagramHesap', 'kisiler'));
    }

    public function mesajlar(
        Request $request,
        InstagramHesap $instagramHesap,
        InstagramKisi $instagramKisi,
        AiHafizaPanelServisi $aiHafizaPanelServisi
    )
    {
        $mesajlar = $instagramKisi->mesajlar()
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $hafizaPaneli = $aiHafizaPanelServisi->instagramKisiPaneli($instagramHesap, $instagramKisi);

        return view('admin.instagram.mesajlar', compact('instagramHesap', 'instagramKisi', 'mesajlar', 'hafizaPaneli'));
    }

    public function aiGorevleri(Request $request, InstagramHesap $instagramHesap)
    {
        $sorgu = $instagramHesap->aiGorevleri()->with(['mesaj', 'kisi']);

        if ($durum = $request->input('durum')) {
            $sorgu->where('durum', $durum);
        }

        $gorevler = $sorgu->latest()->paginate(25)->withQueryString();

        return view('admin.instagram.ai-gorevleri', compact('instagramHesap', 'gorevler'));
    }

    /**
     * Kişiye ait tüm mesajları, AI görevlerini ve hafızaları siler.
     */
    public function kisiVerileriniSil(InstagramHesap $instagramHesap, InstagramKisi $instagramKisi)
    {
        $hesapSahibiId = $instagramHesap->user_id;

        // Kişiye ait AI görevlerini sil
        InstagramAiGorevi::where('instagram_hesap_id', $instagramHesap->id)
            ->where('instagram_kisi_id', $instagramKisi->id)
            ->delete();

        // Kişiye ait mesajları sil
        InstagramMesaj::where('instagram_hesap_id', $instagramHesap->id)
            ->where('instagram_kisi_id', $instagramKisi->id)
            ->delete();

        // Kişiye ait AI hafızalarını sil
        AiHafiza::where('ai_user_id', $hesapSahibiId)
            ->where('hedef_tipi', AiHafiza::HEDEF_TIPI_INSTAGRAM_KISI)
            ->where('hedef_id', $instagramKisi->id)
            ->delete();

        // Kişinin son mesaj tarihini sıfırla
        $instagramKisi->update(['son_mesaj_tarihi' => null]);

        $kisiAdi = $instagramKisi->gorunen_ad ?: $instagramKisi->instagram_kullanici_adi;

        return redirect()
            ->route('admin.instagram.mesajlar', [$instagramHesap, $instagramKisi])
            ->with('basari', "{$kisiAdi} kişisine ait tüm mesajlar, AI görevleri ve hafızalar silindi.");
    }
}
