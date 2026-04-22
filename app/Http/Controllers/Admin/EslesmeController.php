<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eslesme;
use App\Models\User;
use App\Services\Admin\AiHafizaPanelServisi;
use Illuminate\Http\Request;

class EslesmeController extends Controller
{
    public function index(Request $request)
    {
        $sorgu = Eslesme::with(['user', 'eslesenUser', 'sohbet']);

        // Arama
        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->whereHas('user', function ($q2) use ($arama) {
                    $q2->where('ad', 'like', "%{$arama}%")
                        ->orWhere('soyad', 'like', "%{$arama}%")
                        ->orWhere('kullanici_adi', 'like', "%{$arama}%")
                        ->orWhere('email', 'like', "%{$arama}%");
                })
                    ->orWhereHas('eslesenUser', function ($q2) use ($arama) {
                        $q2->where('ad', 'like', "%{$arama}%")
                            ->orWhere('soyad', 'like', "%{$arama}%")
                            ->orWhere('kullanici_adi', 'like', "%{$arama}%")
                            ->orWhere('email', 'like', "%{$arama}%");
                    });
            });
        }

        // Durum filtresi
        if ($durum = $request->input('durum')) {
            $sorgu->where('durum', $durum);
        }

        // Tür filtresi
        if ($tur = $request->input('tur')) {
            $sorgu->where('eslesme_turu', $tur);
        }

        // Kaynak filtresi
        if ($kaynak = $request->input('kaynak')) {
            $sorgu->where('eslesme_kaynagi', $kaynak);
        }

        // İstatistikler
        $istatistikler = [
            'toplam'    => Eslesme::count(),
            'aktif'     => Eslesme::where('durum', 'aktif')->count(),
            'bekliyor'  => Eslesme::where('durum', 'bekliyor')->count(),
            'bitti'     => Eslesme::where('durum', 'bitti')->count(),
            'bugun'     => Eslesme::whereDate('created_at', today())->count(),
        ];

        $eslesmeler = $sorgu->latest()->paginate(25)->withQueryString();

        return view('admin.eslesmeler.index', compact('eslesmeler', 'istatistikler'));
    }

    public function goster(Eslesme $eslesme, AiHafizaPanelServisi $aiHafizaPanelServisi)
    {
        $eslesme->load(['user', 'eslesenUser', 'baslatanUser', 'sohbet']);

        // Sohbet istatistikleri
        $sohbetBilgisi = null;
        if ($eslesme->sohbet) {
            $sohbetBilgisi = [
                'toplam_mesaj' => $eslesme->sohbet->toplam_mesaj_sayisi,
                'son_mesaj'    => $eslesme->sohbet->son_mesaj_tarihi,
                'durum'        => $eslesme->sohbet->durum,
            ];
        }

        $hafizaOzetleri = $aiHafizaPanelServisi->eslesmeHafizaOzetleri($eslesme);

        return view('admin.eslesmeler.goster', compact('eslesme', 'sohbetBilgisi', 'hafizaOzetleri'));
    }

    public function durumGuncelle(Request $request, Eslesme $eslesme)
    {
        $request->validate([
            'durum'        => 'required|in:bekliyor,aktif,bitti,iptal',
            'bitis_sebebi' => 'nullable|string|max:500',
        ]);

        $veri = ['durum' => $request->input('durum')];

        if (in_array($request->input('durum'), ['bitti', 'iptal']) && $request->filled('bitis_sebebi')) {
            $veri['bitis_sebebi'] = $request->input('bitis_sebebi');
        }

        $eslesme->update($veri);

        return redirect()->route('admin.eslesmeler.goster', $eslesme)
            ->with('basari', 'Eşleşme durumu güncellendi.');
    }

    public function sohbet(Eslesme $eslesme, AiHafizaPanelServisi $aiHafizaPanelServisi)
    {
        $eslesme->load(['user', 'eslesenUser', 'sohbet']);

        abort_unless($eslesme->sohbet, 404, 'Bu eşleşmeye ait sohbet bulunamadı.');

        $mesajlar = $eslesme->sohbet->mesajlar()
            ->with('gonderen')
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $hafizaOzetleri = $aiHafizaPanelServisi->eslesmeHafizaOzetleri($eslesme);

        return view('admin.eslesmeler.sohbet', compact('eslesme', 'mesajlar', 'hafizaOzetleri'));
    }

    public function kisiHafiza(Eslesme $eslesme, User $kullanici, AiHafizaPanelServisi $aiHafizaPanelServisi)
    {
        $eslesme->load(['user', 'eslesenUser', 'sohbet']);

        abort_unless(
            in_array($kullanici->id, [$eslesme->user_id, $eslesme->eslesen_user_id], true),
            404,
            'Bu kullanici ilgili eslesmede bulunamadi.'
        );

        $paneller = $aiHafizaPanelServisi->eslesmeKisiPanelleri($eslesme, $kullanici);

        return view('admin.eslesmeler.kisi-hafiza', compact('eslesme', 'kullanici', 'paneller'));
    }
}
