<?php

namespace App\Http\Controllers\Odeme;

use App\Http\Controllers\Controller;
use App\Http\Resources\PuanPaketiResource;
use App\Models\PuanPaketi;
use Illuminate\Http\Request;

class PuanPaketiController extends Controller
{
    public function index(Request $request)
    {
        $platform = $request->query('platform');

        $paketler = PuanPaketi::query()
            ->aktif()
            ->when($platform === 'android', fn($query) => $query->whereNotNull('android_urun_kodu')->where('android_urun_kodu', '!=', ''))
            ->when($platform === 'ios', fn($query) => $query->whereNotNull('ios_urun_kodu')->where('ios_urun_kodu', '!=', ''))
            ->orderBy('sira')
            ->get();

        return PuanPaketiResource::collection($paketler);
    }
}
