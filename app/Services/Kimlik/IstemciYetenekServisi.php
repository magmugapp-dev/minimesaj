<?php

namespace App\Services\Kimlik;

class IstemciYetenekServisi
{
    public function belirle(string $istemciTipi): array
    {
        return match ($istemciTipi) {
            'dating' => ['dating', 'ai', 'odeme'],
            'extension' => ['extension', 'ai'],
            'admin' => ['dating', 'extension', 'ai', 'odeme', 'admin'],
        };
    }
}
