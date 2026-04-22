<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngellenenKullaniciResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $engellenen = $this->engellenen;

        return [
            'id' => $engellenen?->id,
            'ad' => $engellenen?->ad,
            'soyad' => $engellenen?->soyad,
            'kullanici_adi' => $engellenen?->kullanici_adi,
            'profil_resmi' => MediaUrl::resolve($engellenen?->profil_resmi),
            'engellendi_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
