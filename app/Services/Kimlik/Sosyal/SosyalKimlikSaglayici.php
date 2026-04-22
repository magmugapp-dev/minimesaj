<?php

namespace App\Services\Kimlik\Sosyal;

interface SosyalKimlikSaglayici
{
    public function dogrula(array $veri): SosyalKimlikBilgisi;
}
