<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BildirimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = array_merge([
            'tip' => null,
            'baslik' => null,
            'govde' => null,
            'mesaj' => null,
            'rota' => null,
            'rota_parametreleri' => [],
        ], is_array($this->data) ? $this->data : []);

        return [
            'id' => $this->id,
            'tip' => $payload['tip'],
            'baslik' => $payload['baslik'],
            'govde' => $payload['govde'],
            'mesaj' => $payload['mesaj'] ?? $payload['govde'],
            'rota' => $payload['rota'],
            'rota_parametreleri' => $payload['rota_parametreleri'],
            'veri' => $payload,
            'okundu_mu' => $this->read_at !== null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
