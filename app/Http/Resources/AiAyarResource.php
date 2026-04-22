<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiAyarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'aktif_mi' => $this->aktif_mi,
            'saglayici_tipi' => $this->saglayici_tipi,
            'model_adi' => $this->model_adi,
            'yedek_saglayici_tipi' => $this->yedek_saglayici_tipi,
            'yedek_model_adi' => $this->yedek_model_adi,
            'kisilik_tipi' => $this->kisilik_tipi,
            'kisilik_aciklamasi' => $this->kisilik_aciklamasi,
            'konusma_tonu' => $this->konusma_tonu,
            'konusma_stili' => $this->konusma_stili,
            'emoji_seviyesi' => $this->emoji_seviyesi,
            'flort_seviyesi' => $this->flort_seviyesi,
            'giriskenlik_seviyesi' => $this->giriskenlik_seviyesi,
            'utangaclik_seviyesi' => $this->utangaclik_seviyesi,
            'duygusallik_seviyesi' => $this->duygusallik_seviyesi,
            'kiskanclik_seviyesi' => $this->kiskanclik_seviyesi,
            'mizah_seviyesi' => $this->mizah_seviyesi,
            'zeka_seviyesi' => $this->zeka_seviyesi,
            'ilk_mesaj_atar_mi' => $this->ilk_mesaj_atar_mi,
            'ilk_mesaj_sablonu' => $this->ilk_mesaj_sablonu,
            'gunluk_konusma_limiti' => $this->gunluk_konusma_limiti,
            'tek_kullanici_gunluk_mesaj_limiti' => $this->tek_kullanici_gunluk_mesaj_limiti,
            'minimum_cevap_suresi_saniye' => $this->minimum_cevap_suresi_saniye,
            'maksimum_cevap_suresi_saniye' => $this->maksimum_cevap_suresi_saniye,
            'ortalama_mesaj_uzunlugu' => $this->ortalama_mesaj_uzunlugu,
            'mesaj_uzunlugu_min' => $this->mesaj_uzunlugu_min,
            'mesaj_uzunlugu_max' => $this->mesaj_uzunlugu_max,
            'sesli_mesaj_gonderebilir_mi' => $this->sesli_mesaj_gonderebilir_mi,
            'foto_gonderebilir_mi' => $this->foto_gonderebilir_mi,
            'saat_dilimi' => $this->saat_dilimi,
            'uyku_baslangic' => $this->uyku_baslangic,
            'uyku_bitis' => $this->uyku_bitis,
            'hafta_sonu_uyku_baslangic' => $this->hafta_sonu_uyku_baslangic,
            'hafta_sonu_uyku_bitis' => $this->hafta_sonu_uyku_bitis,
            'rastgele_gecikme_dakika' => $this->rastgele_gecikme_dakika,
            'sistem_komutu' => $this->sistem_komutu,
            'yasakli_konular' => $this->yasakli_konular,
            'zorunlu_kurallar' => $this->zorunlu_kurallar,
            'hafiza_aktif_mi' => $this->hafiza_aktif_mi,
            'hafiza_seviyesi' => $this->hafiza_seviyesi,
            'kullaniciyi_hatirlar_mi' => $this->kullaniciyi_hatirlar_mi,
            'temperature' => $this->temperature,
            'top_p' => $this->top_p,
            'max_output_tokens' => $this->max_output_tokens,
        ];
    }
}
