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
        $paketler = PuanPaketi::query()
            ->aktif()
            ->orderBy('sira')
            ->get();

        return PuanPaketiResource::collection($paketler);
    }
}
