<?php

namespace App\Http\Resources;

use App\Models\HediyeGonderimi;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HediyeGonderimiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var HediyeGonderimi $gonderim */
        $gonderim = $this->resource;
        $gonderen = $gonderim->gonderen;

        return [
            'id' => $gonderim->id,
            'hediye_id' => $gonderim->hediye_id,
            'hediye_adi' => $gonderim->hediye_adi,
            'hediye_ikon' => $gonderim->hediye?->ikon,
            'puan_bedeli' => $gonderim->puan_bedeli,
            'gonderen' => $gonderen ? [
                'id' => $gonderen->id,
                'ad' => $gonderen->ad,
                'soyad' => $gonderen->soyad,
                'kullanici_adi' => $gonderen->kullanici_adi,
                'profil_resmi' => MediaUrl::resolve($gonderen->profil_resmi),
            ] : null,
            'created_at' => $gonderim->created_at,
        ];
    }
}
