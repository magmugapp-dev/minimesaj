<?php

namespace App\Http\Resources;

use App\Models\AbonelikPaketi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbonelikPaketiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AbonelikPaketi $paket */
        $paket = $this->resource;
        $platform = $request->query('platform');

        return [
            'id' => $paket->id,
            'kod' => $paket->kod,
            'android_urun_kodu' => $paket->android_urun_kodu,
            'ios_urun_kodu' => $paket->ios_urun_kodu,
            'magaza_urun_kodu' => $platform ? $paket->magazaUrunKodu($platform) : null,
            'sure_ay' => $paket->sure_ay,
            'fiyat' => (float) $paket->fiyat,
            'para_birimi' => $paket->para_birimi,
            'rozet' => $paket->rozet,
            'onerilen_mi' => $paket->onerilen_mi,
            'aktif' => $paket->aktif,
            'sira' => $paket->sira,
            'urun_tipi' => 'abonelik',
        ];
    }
}
