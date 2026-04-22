<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Engelleme;
use Illuminate\Http\Request;

class EngelController extends Controller
{
    public function index(Request $request)
    {
        $sorgu = Engelleme::with(['engelleyen', 'engellenen']);

        // Arama
        if ($arama = $request->input('arama')) {
            $sorgu->where(function ($q) use ($arama) {
                $q->where('sebep', 'like', "%{$arama}%")
                  ->orWhereHas('engelleyen', function ($q2) use ($arama) {
                      $q2->where('ad', 'like', "%{$arama}%")
                         ->orWhere('soyad', 'like', "%{$arama}%")
                         ->orWhere('email', 'like', "%{$arama}%");
                  })
                  ->orWhereHas('engellenen', function ($q2) use ($arama) {
                      $q2->where('ad', 'like', "%{$arama}%")
                         ->orWhere('soyad', 'like', "%{$arama}%")
                         ->orWhere('email', 'like', "%{$arama}%");
                  });
            });
        }

        // İstatistikler
        $istatistikler = [
            'toplam'   => Engelleme::count(),
            'bugun'    => Engelleme::whereDate('created_at', today())->count(),
            'bu_hafta' => Engelleme::where('created_at', '>=', now()->startOfWeek())->count(),
        ];

        $engeller = $sorgu->latest()->paginate(25)->withQueryString();

        return view('admin.moderasyon.engeller.index', compact('engeller', 'istatistikler'));
    }

    public function kaldir(Engelleme $engelleme)
    {
        $engelleme->delete();

        return redirect()->route('admin.moderasyon.engeller')
            ->with('basari', 'Engelleme kaldırıldı.');
    }
}
