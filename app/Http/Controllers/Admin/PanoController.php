<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eslesme;
use App\Models\InstagramHesap;
use App\Models\Mesaj;
use App\Models\Odeme;
use App\Models\Sikayet;
use App\Models\User;

class PanoController extends Controller
{
    public function index()
    {
        $istatistikler = [
            'toplam_kullanici' => User::where('hesap_tipi', 'user')->count(),
            'toplam_ai' => User::where('hesap_tipi', 'ai')->count(),
            'bugunun_kayitlari' => User::whereDate('created_at', today())->count(),
            'aktif_eslesmeler' => Eslesme::where('durum', 'aktif')->count(),
            'bugunun_mesajlari' => Mesaj::whereDate('created_at', today())->count(),
            'bekleyen_sikayetler' => Sikayet::whereIn('durum', [Sikayet::DURUM_BEKLIYOR, 'beklemede'])->count(),
            'toplam_gelir' => Odeme::where('durum', 'basarili')->sum('tutar'),
            'cevrimici_gercek' => User::where('cevrim_ici_mi', true)->where('hesap_tipi', 'user')->count(),
            'cevrimici_ai' => User::where('cevrim_ici_mi', true)->where('hesap_tipi', 'ai')->count(),
            'instagram_hesaplar' => InstagramHesap::count(),
        ];

        $sonSikayetler = Sikayet::query()
            ->where('hedef_tipi', Sikayet::HEDEF_TIPI_USER)
            ->with(['sikayetEden:id,ad,kullanici_adi', 'hedefUser:id,ad,kullanici_adi'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.pano', compact('istatistikler', 'sonSikayetler'));
    }
}
