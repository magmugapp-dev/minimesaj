<?php

namespace App\Http\Controllers\Yonetim;

use App\Http\Controllers\Controller;
use App\Models\IstatistikOzeti;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IstatistikController extends Controller
{
    public function gunlukOzet(Request $request): JsonResponse
    {
        $tarih = $request->input('tarih', today()->toDateString());

        $ozet = IstatistikOzeti::where('tarih', $tarih)->first();

        if (!$ozet) {
            // Gerçek zamanlı hesapla
            $ozet = [
                'tarih' => $tarih,
                'toplam_kullanici' => DB::table('users')->count(),
                'yeni_kayit' => DB::table('users')->whereDate('created_at', $tarih)->count(),
                'aktif_kullanici' => DB::table('users')->whereDate('son_aktiflik_tarihi', $tarih)->count(),
                'toplam_mesaj' => DB::table('mesajlar')->whereDate('created_at', $tarih)->count(),
                'toplam_eslesme' => DB::table('eslesmeler')->whereDate('created_at', $tarih)->count(),
                'toplam_sikayet' => DB::table('sikayetler')->whereDate('created_at', $tarih)->count(),
            ];
        }

        return response()->json($ozet);
    }
}
