<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Begeni;
use App\Models\Engelleme;
use App\Models\Eslesme;
use App\Models\InstagramAiGorevi;
use App\Models\InstagramHesap;
use App\Models\InstagramMesaj;
use App\Models\Mesaj;
use App\Models\Odeme;
use App\Models\PuanHareketi;
use App\Models\Sikayet;
use App\Models\Sohbet;
use App\Models\User;
use Illuminate\Support\Carbon;

class IstatistikController extends Controller
{
    public function index()
    {
        // ── Kullanıcı İstatistikleri ──
        $kullanici = [
            'toplam'       => User::where('hesap_tipi', 'user')->count(),
            'ai'           => User::where('hesap_tipi', 'ai')->count(),
            'aktif'        => User::where('hesap_durumu', 'aktif')->count(),
            'pasif'        => User::where('hesap_durumu', 'pasif')->count(),
            'yasakli'      => User::where('hesap_durumu', 'yasakli')->count(),
            'premium'      => User::where('premium_aktif_mi', true)->count(),
            'cevrimici'    => User::where('cevrim_ici_mi', true)->count(),
            'bugun'        => User::whereDate('created_at', today())->count(),
            'bu_hafta'     => User::where('created_at', '>=', now()->startOfWeek())->count(),
            'bu_ay'        => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'erkek'        => User::where('hesap_tipi', 'user')->where('cinsiyet', 'erkek')->count(),
            'kadin'        => User::where('hesap_tipi', 'user')->where('cinsiyet', 'kadin')->count(),
        ];

        // ── Eşleşme İstatistikleri ──
        $eslesme = [
            'toplam'       => Eslesme::count(),
            'aktif'        => Eslesme::where('durum', 'aktif')->count(),
            'bekliyor'     => Eslesme::where('durum', 'bekliyor')->count(),
            'bitti'        => Eslesme::where('durum', 'bitti')->count(),
            'iptal'        => Eslesme::where('durum', 'iptal')->count(),
            'bugun'        => Eslesme::whereDate('created_at', today())->count(),
            'bu_hafta'     => Eslesme::where('created_at', '>=', now()->startOfWeek())->count(),
            'rastgele'     => Eslesme::where('eslesme_turu', 'rastgele')->count(),
            'otomatik'     => Eslesme::where('eslesme_turu', 'otomatik')->count(),
            'premium'      => Eslesme::where('eslesme_turu', 'premium')->count(),
            'ai_kaynakli'  => Eslesme::where('eslesme_kaynagi', 'yapay_zeka')->count(),
            'gercek'       => Eslesme::where('eslesme_kaynagi', 'gercek_kullanici')->count(),
        ];

        // ── Beğeni İstatistikleri ──
        $begeni = [
            'toplam'       => Begeni::count(),
            'karsilikli'   => Begeni::where('eslesmeye_donustu_mu', true)->count(),
            'bugun'        => Begeni::whereDate('created_at', today())->count(),
            'bu_hafta'     => Begeni::where('created_at', '>=', now()->startOfWeek())->count(),
        ];

        // ── Mesaj İstatistikleri ──
        $mesaj = [
            'toplam'       => Mesaj::count(),
            'bugun'        => Mesaj::whereDate('created_at', today())->count(),
            'bu_hafta'     => Mesaj::where('created_at', '>=', now()->startOfWeek())->count(),
            'metin'        => Mesaj::where('mesaj_tipi', 'metin')->count(),
            'ses'          => Mesaj::where('mesaj_tipi', 'ses')->count(),
            'foto'         => Mesaj::where('mesaj_tipi', 'foto')->count(),
            'sistem'       => Mesaj::where('mesaj_tipi', 'sistem')->count(),
            'ai_uretilmis' => Mesaj::where('ai_tarafindan_uretildi_mi', true)->count(),
            'aktif_sohbet' => Sohbet::where('durum', 'aktif')->count(),
        ];

        // ── Moderasyon İstatistikleri ──
        $moderasyon = [
            'sikayet_toplam'    => Sikayet::count(),
            'sikayet_bekliyor'  => Sikayet::where('durum', 'bekliyor')->count(),
            'sikayet_inceleniyor' => Sikayet::where('durum', 'inceleniyor')->count(),
            'sikayet_cozuldu'   => Sikayet::where('durum', 'cozuldu')->count(),
            'sikayet_reddedildi' => Sikayet::where('durum', 'reddedildi')->count(),
            'engel_toplam'      => Engelleme::count(),
            'engel_bugun'       => Engelleme::whereDate('created_at', today())->count(),
        ];

        // ── Finansal İstatistikler ──
        $finansal = [
            'toplam_gelir'      => Odeme::where('durum', 'basarili')->sum('tutar'),
            'bugun_gelir'       => Odeme::where('durum', 'basarili')->whereDate('created_at', today())->sum('tutar'),
            'bu_hafta_gelir'    => Odeme::where('durum', 'basarili')->where('created_at', '>=', now()->startOfWeek())->sum('tutar'),
            'bu_ay_gelir'       => Odeme::where('durum', 'basarili')->where('created_at', '>=', now()->startOfMonth())->sum('tutar'),
            'toplam_islem'      => Odeme::count(),
            'basarili_islem'    => Odeme::where('durum', 'basarili')->count(),
            'ios_gelir'         => Odeme::where('durum', 'basarili')->where('platform', 'ios')->sum('tutar'),
            'android_gelir'     => Odeme::where('durum', 'basarili')->where('platform', 'android')->sum('tutar'),
            'puan_kazanilan'    => PuanHareketi::where('puan_miktari', '>', 0)->sum('puan_miktari'),
            'puan_harcanan'     => abs(PuanHareketi::where('puan_miktari', '<', 0)->sum('puan_miktari')),
        ];

        // ── Instagram İstatistikleri ──
        $instagram = [
            'hesap_toplam'      => InstagramHesap::count(),
            'hesap_bagli'       => InstagramHesap::where('aktif_mi', true)->count(),
            'hesap_oto_yanit'   => InstagramHesap::where('otomatik_cevap_aktif_mi', true)->count(),
            'mesaj_toplam'      => InstagramMesaj::count(),
            'mesaj_bugun'       => InstagramMesaj::whereDate('created_at', today())->count(),
            'mesaj_gelen'       => InstagramMesaj::where('gonderen_tipi', 'karsi_taraf')->count(),
            'mesaj_giden'       => InstagramMesaj::where('gonderen_tipi', 'biz')->count(),
            'mesaj_ai'          => InstagramMesaj::where('gonderen_tipi', 'ai')->count(),
            'ai_gorev_toplam'   => InstagramAiGorevi::count(),
            'ai_gorev_basarili' => InstagramAiGorevi::where('durum', 'tamamlandi')->count(),
            'ai_gorev_basarisiz' => InstagramAiGorevi::where('durum', 'basarisiz')->count(),
        ];

        // ── Son 7 Gün Trend (grafik için) ──
        $gunler = collect(range(6, 0))->map(fn ($i) => Carbon::today()->subDays($i));

        $trendler = [
            'tarihler' => $gunler->map(fn ($g) => $g->format('d.m'))->values()->all(),
            'kayitlar' => $gunler->map(fn ($g) => User::whereDate('created_at', $g)->count())->values()->all(),
            'eslesmeler' => $gunler->map(fn ($g) => Eslesme::whereDate('created_at', $g)->count())->values()->all(),
            'mesajlar' => $gunler->map(fn ($g) => Mesaj::whereDate('created_at', $g)->count())->values()->all(),
            'gelir' => $gunler->map(fn ($g) => (float) Odeme::where('durum', 'basarili')->whereDate('created_at', $g)->sum('tutar'))->values()->all(),
        ];

        return view('admin.istatistik.index', compact(
            'kullanici',
            'eslesme',
            'begeni',
            'mesaj',
            'moderasyon',
            'finansal',
            'instagram',
            'trendler',
        ));
    }
}
