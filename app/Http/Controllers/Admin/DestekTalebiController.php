<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DestekTalebi;
use App\Models\DestekTalebiYaniti;
use App\Notifications\DestekTalebiYanitiOlustu;
use App\Services\AyarServisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DestekTalebiController extends Controller
{
    public function __construct(private AyarServisi $ayarServisi) {}

    public function index(Request $request): View
    {
        $sorgu = DestekTalebi::with('user');

        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('mesaj', 'like', "%{$arama}%")
                    ->orWhereHas('user', function ($q2) use ($arama) {
                        $q2->where('ad', 'like', "%{$arama}%")
                            ->orWhere('soyad', 'like', "%{$arama}%")
                            ->orWhere('email', 'like', "%{$arama}%");
                    });
            });
        }

        if ($durum = $request->input('durum')) {
            $sorgu->where('durum', $durum);
        }

        $istatistikler = [
            'toplam' => DestekTalebi::count(),
            'yeni' => DestekTalebi::where('durum', 'yeni')->count(),
            'inceleniyor' => DestekTalebi::where('durum', 'inceleniyor')->count(),
            'cozuldu' => DestekTalebi::where('durum', 'cozuldu')->count(),
        ];

        $destekTalepleri = $sorgu->latest()->paginate(25)->withQueryString();

        return view('admin.moderasyon.destek-talepleri.index', compact('destekTalepleri', 'istatistikler'));
    }

    public function goster(DestekTalebi $destekTalebi): View
    {
        $destekTalebi->load(['user', 'yanitlar.admin']);

        $benzerTalepler = DestekTalebi::with('user')
            ->where('user_id', $destekTalebi->user_id)
            ->where('id', '!=', $destekTalebi->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.moderasyon.destek-talepleri.goster', compact('destekTalebi', 'benzerTalepler'));
    }

    public function durumGuncelle(Request $request, DestekTalebi $destekTalebi): RedirectResponse
    {
        $request->validate([
            'durum' => 'required|in:yeni,inceleniyor,cozuldu',
            'yonetici_notu' => 'nullable|string|max:2000',
        ]);

        $destekTalebi->update([
            'durum' => $request->string('durum')->toString(),
            'yonetici_notu' => $request->filled('yonetici_notu')
                ? trim((string) $request->input('yonetici_notu'))
                : null,
        ]);

        return redirect()->route('admin.moderasyon.destek-talepleri.goster', $destekTalebi)
            ->with('basari', 'Destek talebi durumu guncellendi.');
    }

    public function yanitEkle(Request $request, DestekTalebi $destekTalebi): RedirectResponse
    {
        $request->validate([
            'mesaj' => 'required|string|min:3|max:2000',
            'kullaniciya_eposta_gonder' => 'nullable|boolean',
        ]);

        $yanit = DestekTalebiYaniti::create([
            'destek_talebi_id' => $destekTalebi->id,
            'admin_user_id' => $request->user()->id,
            'mesaj' => trim((string) $request->input('mesaj')),
        ]);

        if ($request->boolean('kullaniciya_eposta_gonder') && filled($destekTalebi->user?->email)) {
            $destekTalebi->user->notify(new DestekTalebiYanitiOlustu(
                talep: $destekTalebi,
                yanit: $yanit,
                uygulamaAdi: (string) $this->ayarServisi->al('uygulama_adi', 'MiniMesaj'),
            ));
        }

        return redirect()->route('admin.moderasyon.destek-talepleri.goster', $destekTalebi)
            ->with('basari', 'Destek talebine yanit eklendi.');
    }
}
