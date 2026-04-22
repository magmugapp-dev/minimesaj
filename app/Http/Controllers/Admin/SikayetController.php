<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sikayet;
use App\Models\User;
use Illuminate\Http\Request;

class SikayetController extends Controller
{
    public function index(Request $request)
    {
        $sorgu = Sikayet::with('sikayetEden');

        // Arama
        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('kategori', 'like', "%{$arama}%")
                  ->orWhere('aciklama', 'like', "%{$arama}%")
                  ->orWhereHas('sikayetEden', function ($q2) use ($arama) {
                      $q2->where('ad', 'like', "%{$arama}%")
                         ->orWhere('soyad', 'like', "%{$arama}%")
                         ->orWhere('email', 'like', "%{$arama}%");
                  });
            });
        }

        // Durum filtresi
        if ($durum = $request->input('durum')) {
            $sorgu->where('durum', $durum);
        }

        // Kategori filtresi
        if ($kategori = $request->input('kategori')) {
            $sorgu->where('kategori', $kategori);
        }

        // Hedef tipi filtresi
        if ($hedefTipi = $request->input('hedef_tipi')) {
            $sorgu->where('hedef_tipi', $hedefTipi);
        }

        // İstatistikler
        $istatistikler = [
            'toplam'      => Sikayet::count(),
            'bekliyor'    => Sikayet::where('durum', 'bekliyor')->count(),
            'inceleniyor' => Sikayet::where('durum', 'inceleniyor')->count(),
            'cozuldu'     => Sikayet::where('durum', 'cozuldu')->count(),
            'reddedildi'  => Sikayet::where('durum', 'reddedildi')->count(),
        ];

        // Kategoriler (distinct)
        $kategoriler = Sikayet::distinct()->pluck('kategori')->sort()->values();

        $sikayetler = $sorgu->latest()->paginate(25)->withQueryString();

        return view('admin.moderasyon.sikayetler.index', compact('sikayetler', 'istatistikler', 'kategoriler'));
    }

    public function goster(Sikayet $sikayet)
    {
        $sikayet->load('sikayetEden');

        // Hedef bilgisini çöz
        $hedef = null;
        if ($sikayet->hedef_tipi === 'user') {
            $hedef = User::find($sikayet->hedef_id);
        } elseif ($sikayet->hedef_tipi === 'mesaj') {
            $hedef = \App\Models\Mesaj::with('gonderen')->find($sikayet->hedef_id);
        }

        // Aynı hedef hakkındaki diğer şikayetler
        $benzerSikayetler = Sikayet::with('sikayetEden')
            ->where('hedef_tipi', $sikayet->hedef_tipi)
            ->where('hedef_id', $sikayet->hedef_id)
            ->where('id', '!=', $sikayet->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.moderasyon.sikayetler.goster', compact('sikayet', 'hedef', 'benzerSikayetler'));
    }

    public function durumGuncelle(Request $request, Sikayet $sikayet)
    {
        $request->validate([
            'durum'         => 'required|in:bekliyor,inceleniyor,cozuldu,reddedildi',
            'yonetici_notu' => 'nullable|string|max:2000',
        ]);

        $sikayet->update([
            'durum'         => $request->input('durum'),
            'yonetici_notu' => $request->input('yonetici_notu'),
        ]);

        return redirect()->route('admin.moderasyon.sikayetler.goster', $sikayet)
            ->with('basari', 'Şikayet durumu güncellendi.');
    }

    public function topluDurumGuncelle(Request $request)
    {
        $request->validate([
            'sikayet_idler' => 'required|array|min:1',
            'sikayet_idler.*' => 'integer|exists:sikayetler,id',
            'durum' => 'required|in:bekliyor,inceleniyor,cozuldu,reddedildi',
        ]);

        $guncellenen = Sikayet::whereIn('id', $request->input('sikayet_idler'))
            ->update(['durum' => $request->input('durum')]);

        return redirect()->route('admin.moderasyon.sikayetler')
            ->with('basari', "{$guncellenen} şikayet güncellendi.");
    }
}
