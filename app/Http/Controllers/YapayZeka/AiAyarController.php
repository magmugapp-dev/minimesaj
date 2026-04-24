<?php

namespace App\Http\Controllers\YapayZeka;

use App\Http\Controllers\Controller;
use App\Http\Requests\YapayZeka\AiAyarGuncelleRequest;
use App\Http\Resources\AiAyarResource;
use App\Models\AiAyar;
use App\Services\YapayZeka\GeminiSaglayici;
use Illuminate\Http\Request;

class AiAyarController extends Controller
{
    public function goster(Request $request): AiAyarResource
    {
        $ayar = AiAyar::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['saglayici_tipi' => 'gemini', 'model_adi' => GeminiSaglayici::MODEL_ADI]
        );

        return new AiAyarResource($ayar);
    }

    public function guncelle(AiAyarGuncelleRequest $request): AiAyarResource
    {
        $veri = $request->validated();

        if (($veri['saglayici_tipi'] ?? null) === 'gemini') {
            $veri['model_adi'] = GeminiSaglayici::MODEL_ADI;
        }

        if (($veri['yedek_saglayici_tipi'] ?? null) === 'gemini') {
            $veri['yedek_model_adi'] = GeminiSaglayici::MODEL_ADI;
        }

        $ayar = AiAyar::updateOrCreate(
            ['user_id' => $request->user()->id],
            $veri
        );

        return new AiAyarResource($ayar);
    }
}
