<?php

namespace App\Http\Resources;

use App\Models\Hediye;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HediyeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Hediye $hediye */
        $hediye = $this->resource;

        return [
            'id' => $hediye->id,
            'kod' => $hediye->kod,
            'ad' => $hediye->ad,
            'ikon' => $hediye->ikon,
            'puan_bedeli' => $hediye->puan_bedeli,
            'aktif' => $hediye->aktif,
            'sira' => $hediye->sira,
        ];
    }
}
