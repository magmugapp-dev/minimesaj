<?php

namespace App\Http\Controllers\Moderasyon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Moderasyon\SikayetOlusturRequest;
use App\Http\Resources\SikayetResource;
use App\Models\Sikayet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SikayetController extends Controller
{
    public function olustur(SikayetOlusturRequest $request): JsonResponse
    {
        $veri = $request->validated();

        $sikayet = Sikayet::create([
            'sikayet_eden_user_id' => $request->user()->id,
            ...$veri,
        ]);

        return (new SikayetResource($sikayet))
            ->response()
            ->setStatusCode(201);
    }
}
