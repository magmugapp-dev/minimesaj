<?php

namespace App\Helpers;

class SohbetBitirenHelper
{
    // Sohbeti bitiren mesajları tespit eden yardımcı fonksiyon
    public static function mesajSohbetBitirenMi(string $mesaj): bool
    {
        $mesaj = mb_strtolower(trim($mesaj));
        $anahtarlar = [
            'görüşürüz',
            'görüşmek üzere',
            'kendine iyi bak',
            'bye',
            'hoşça kal',
            'iyi geceler',
            'iyi akşamlar',
            'iyi günler',
            'see you',
            'see ya',
            'take care',
        ];
        foreach ($anahtarlar as $anahtar) {
            if (str_contains($mesaj, $anahtar)) {
                return true;
            }
        }
        return false;
    }
}
