<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Odeme;
use App\Models\PuanHareketi;
use Illuminate\Http\Request;

class FinansalController extends Controller
{
    public function odemeler(Request $request)
    {
        $sorgu = Odeme::with('user');

        // Arama
        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('islem_kodu', 'like', "%{$arama}%")
                    ->orWhere('urun_kodu', 'like', "%{$arama}%")
                    ->orWhereHas('user', function ($q2) use ($arama) {
                        $q2->where('ad', 'like', "%{$arama}%")
                            ->orWhere('kullanici_adi', 'like', "%{$arama}%")
                            ->orWhere('email', 'like', "%{$arama}%");
                    });
            });
        }

        // Durum filtresi
        if ($durum = $request->input('durum')) {
            $sorgu->where('durum', $durum);
        }

        // Platform filtresi
        if ($platform = $request->input('platform')) {
            $sorgu->where('platform', $platform);
        }

        // Ürün tipi filtresi
        if ($urunTipi = $request->input('urun_tipi')) {
            $sorgu->where('urun_tipi', $urunTipi);
        }

        // Doğrulama durumu filtresi
        if ($dogrulama = $request->input('dogrulama')) {
            $sorgu->where('dogrulama_durumu', $dogrulama);
        }

        // İstatistikler
        $istatistikler = [
            'toplam_gelir'      => Odeme::where('durum', 'basarili')->sum('tutar'),
            'bugun_gelir'       => Odeme::where('durum', 'basarili')->whereDate('created_at', today())->sum('tutar'),
            'bu_ay_gelir'       => Odeme::where('durum', 'basarili')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('tutar'),
            'toplam_islem'      => Odeme::count(),
            'basarili'          => Odeme::where('durum', 'basarili')->count(),
            'bekliyor'          => Odeme::where('durum', 'bekliyor')->count(),
            'ios'               => Odeme::where('platform', 'ios')->where('durum', 'basarili')->sum('tutar'),
            'android'           => Odeme::where('platform', 'android')->where('durum', 'basarili')->sum('tutar'),
            'abonelik'          => Odeme::where('urun_tipi', 'abonelik')->where('durum', 'basarili')->count(),
            'tek_seferlik'      => Odeme::where('urun_tipi', 'tek_seferlik')->where('durum', 'basarili')->count(),
        ];

        $durumlar = Odeme::distinct()->pluck('durum')->toArray();

        $odemeler = $sorgu->latest()->paginate(25)->withQueryString();

        return view('admin.finansal.odemeler', compact('odemeler', 'istatistikler', 'durumlar'));
    }

    public function odemeGoster(Odeme $odeme)
    {
        $odeme->load('user');

        $kullaniciOdemeleri = Odeme::where('user_id', $odeme->user_id)
            ->where('id', '!=', $odeme->id)
            ->latest()
            ->take(5)
            ->get();

        return view('admin.finansal.odeme-detay', compact('odeme', 'kullaniciOdemeleri'));
    }

    public function puanHareketleri(Request $request)
    {
        $sorgu = PuanHareketi::with('user');

        // Arama
        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('aciklama', 'like', "%{$arama}%")
                    ->orWhereHas('user', function ($q2) use ($arama) {
                        $q2->where('ad', 'like', "%{$arama}%")
                            ->orWhere('kullanici_adi', 'like', "%{$arama}%")
                            ->orWhere('email', 'like', "%{$arama}%");
                    });
            });
        }

        // İşlem tipi filtresi
        if ($islemTipi = $request->input('islem_tipi')) {
            $sorgu->where('islem_tipi', $islemTipi);
        }

        // İstatistikler
        $istatistikler = [
            'toplam_hareket'    => PuanHareketi::count(),
            'bugun_hareket'     => PuanHareketi::whereDate('created_at', today())->count(),
            'toplam_kazanilan'  => PuanHareketi::where('puan_miktari', '>', 0)->sum('puan_miktari'),
            'toplam_harcanan'   => abs(PuanHareketi::where('puan_miktari', '<', 0)->sum('puan_miktari')),
            'reklam'            => PuanHareketi::where('islem_tipi', 'reklam')->count(),
            'odeme'             => PuanHareketi::where('islem_tipi', 'odeme')->count(),
            'harcama'           => PuanHareketi::where('islem_tipi', 'harcama')->count(),
            'gunluk_hak'        => PuanHareketi::where('islem_tipi', 'gunluk_hak')->count(),
            'hediye'            => PuanHareketi::where('islem_tipi', 'hediye')->count(),
            'yonetici'          => PuanHareketi::where('islem_tipi', 'yonetici')->count(),
        ];

        $puanHareketleri = $sorgu->latest()->paginate(25)->withQueryString();

        return view('admin.finansal.puan-hareketleri', compact('puanHareketleri', 'istatistikler'));
    }

    public function aboneler(Request $request)
    {
        $sorgu = Odeme::query()
            ->with('user')
            ->where('urun_tipi', 'abonelik')
            ->where('durum', 'basarili');

        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('urun_kodu', 'like', "%{$arama}%")
                    ->orWhere('islem_kodu', 'like', "%{$arama}%")
                    ->orWhereHas('user', function ($q2) use ($arama) {
                        $q2->where('ad', 'like', "%{$arama}%")
                            ->orWhere('kullanici_adi', 'like', "%{$arama}%")
                            ->orWhere('email', 'like', "%{$arama}%");
                    });
            });
        }

        if ($platform = $request->input('platform')) {
            $sorgu->where('platform', $platform);
        }

        $abonelikler = $sorgu->latest()->paginate(25)->withQueryString();

        $istatistikler = [
            'toplam_abone' => Odeme::query()->where('urun_tipi', 'abonelik')->where('durum', 'basarili')->count(),
            'aktif_premium' => \App\Models\User::query()
                ->where('premium_aktif_mi', true)
                ->where(function ($query) {
                    $query->whereNull('premium_bitis_tarihi')
                        ->orWhere('premium_bitis_tarihi', '>=', now());
                })
                ->count(),
            'bu_ay_yeni_abone' => Odeme::query()
                ->where('urun_tipi', 'abonelik')
                ->where('durum', 'basarili')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'abonelik_geliri' => Odeme::query()
                ->where('urun_tipi', 'abonelik')
                ->where('durum', 'basarili')
                ->sum('tutar'),
        ];

        return view('admin.finansal.aboneler', compact('abonelikler', 'istatistikler'));
    }
}
