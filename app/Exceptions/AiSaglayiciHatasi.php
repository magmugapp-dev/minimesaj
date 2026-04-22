<?php

namespace App\Exceptions;

use RuntimeException;

class AiSaglayiciHatasi extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $saglayici,
        public readonly ?string $model = null,
        public readonly bool $yenidenDenenebilir = false,
        public readonly ?int $durumKodu = null,
        public readonly array $baglam = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function logBaglami(): array
    {
        return array_filter([
            'hata' => $this->getMessage(),
            'saglayici' => $this->saglayici,
            'model' => $this->model,
            'durum_kodu' => $this->durumKodu,
            'yeniden_denenebilir' => $this->yenidenDenenebilir,
            'baglam' => $this->baglam ?: null,
        ], static fn ($deger) => $deger !== null);
    }
}
