<?php

namespace App\Http\Controllers\Odeme;

use App\Http\Controllers\Controller;
use App\Http\Resources\AbonelikPaketiResource;
use App\Models\AbonelikPaketi;
use Illuminate\Http\Request;

class AbonelikPaketiController extends Controller
{
    public function index(Request $request)
    {
        $paketler = AbonelikPaketi::query()
            ->aktif()
            ->orderBy('sira')
            ->get();

        return AbonelikPaketiResource::collection($paketler);
    }
}
