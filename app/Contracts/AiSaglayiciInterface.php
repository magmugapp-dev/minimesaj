<?php

namespace App\Contracts;

/**
 * Tum AI saglayicilarinin (Gemini, OpenAI vb.) uymasi gereken kontrat.
 */
interface AiSaglayiciInterface
{

    public function tamamla(array $mesajlar, array $parametreler = []): array;

    public function tamamlaStream(array $mesajlar, array $parametreler = [], ?callable $parcaCallback = null): array;

    /**
     * Saglayici adini dondurur (gemini / openai).
     */
    public function saglayiciAdi(): string;
}
