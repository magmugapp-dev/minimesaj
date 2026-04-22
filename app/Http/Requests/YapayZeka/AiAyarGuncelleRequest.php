<?php

namespace App\Http\Requests\YapayZeka;

use Illuminate\Foundation\Http\FormRequest;

class AiAyarGuncelleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aktif_mi' => 'sometimes|boolean',
            'saglayici_tipi' => 'sometimes|in:gemini,openai',
            'model_adi' => 'sometimes|string|max:100',
            'yedek_saglayici_tipi' => 'nullable|in:gemini,openai',
            'yedek_model_adi' => 'nullable|string|max:100',
            'kisilik_tipi' => 'sometimes|string|max:100',
            'kisilik_aciklamasi' => 'nullable|string|max:2000',
            'konusma_tonu' => 'sometimes|string|max:100',
            'konusma_stili' => 'sometimes|string|max:100',
            'emoji_seviyesi' => 'sometimes|integer|min:0|max:10',
            'flort_seviyesi' => 'sometimes|integer|min:0|max:10',
            'giriskenlik_seviyesi' => 'sometimes|integer|min:0|max:10',
            'utangaclik_seviyesi' => 'sometimes|integer|min:0|max:10',
            'duygusallik_seviyesi' => 'sometimes|integer|min:0|max:10',
            'kiskanclik_seviyesi' => 'sometimes|integer|min:0|max:10',
            'mizah_seviyesi' => 'sometimes|integer|min:0|max:10',
            'zeka_seviyesi' => 'sometimes|integer|min:0|max:10',
            'ilk_mesaj_atar_mi' => 'sometimes|boolean',
            'ilk_mesaj_sablonu' => 'nullable|string|max:1000',
            'gunluk_konusma_limiti' => 'sometimes|integer|min:0',
            'tek_kullanici_gunluk_mesaj_limiti' => 'sometimes|integer|min:0',
            'minimum_cevap_suresi_saniye' => 'sometimes|integer|min:0',
            'maksimum_cevap_suresi_saniye' => 'sometimes|integer|min:0',
            'ortalama_mesaj_uzunlugu' => 'sometimes|integer|min:10',
            'mesaj_uzunlugu_min' => 'sometimes|integer|min:1',
            'mesaj_uzunlugu_max' => 'sometimes|integer|min:10',
            'sesli_mesaj_gonderebilir_mi' => 'sometimes|boolean',
            'foto_gonderebilir_mi' => 'sometimes|boolean',
            'saat_dilimi' => 'sometimes|string|timezone',
            'uyku_baslangic' => 'sometimes|date_format:H:i',
            'uyku_bitis' => 'sometimes|date_format:H:i',
            'hafta_sonu_uyku_baslangic' => 'nullable|date_format:H:i',
            'hafta_sonu_uyku_bitis' => 'nullable|date_format:H:i',
            'rastgele_gecikme_dakika' => 'sometimes|integer|min:0|max:60',
            'sistem_komutu' => 'nullable|string|max:5000',
            'yasakli_konular' => 'nullable|array',
            'zorunlu_kurallar' => 'nullable|array',
            'hafiza_aktif_mi' => 'sometimes|boolean',
            'hafiza_seviyesi' => 'sometimes|string|max:50',
            'kullaniciyi_hatirlar_mi' => 'sometimes|boolean',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'top_p' => 'sometimes|numeric|min:0|max:1',
            'max_output_tokens' => 'sometimes|integer|min:50|max:8192',
        ];
    }
}
