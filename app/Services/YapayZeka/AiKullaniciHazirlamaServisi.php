<?php

namespace App\Services\YapayZeka;

use App\Models\AiAyar;
use App\Models\Ayar;
use App\Models\User;
use Illuminate\Support\Str;

class AiKullaniciHazirlamaServisi
{
    public function hazirla(User $aiUser): AiAyar
    {
        if ($aiUser->hesap_tipi !== 'ai') {
            throw new \InvalidArgumentException('Sadece AI kullanicilar hazirlanabilir.');
        }

        $mevcutAyar = $aiUser->relationLoaded('aiAyar')
            ? $aiUser->getRelation('aiAyar')
            : $aiUser->aiAyar()->first();

        $ayar = $aiUser->aiAyar()->updateOrCreate(
            ['user_id' => $aiUser->id],
            $this->varsayilanAyarlar($aiUser, $mevcutAyar)
        );

        $aiUser->setRelation('aiAyar', $ayar);

        return $ayar;
    }

    private function varsayilanAyarlar(User $aiUser, ?AiAyar $mevcutAyar): array
    {
        $varsayilanTemperature = (float) (Ayar::query()->where('anahtar', 'ai_temperature')->value('deger') ?: 0.9);
        $varsayilanMaxToken = (int) (Ayar::query()->where('anahtar', 'ai_max_token')->value('deger') ?: 1024);
        $kisilik = $mevcutAyar?->kisilik_tipi ?: $this->kisilikSec($aiUser);
        $ton = $mevcutAyar?->konusma_tonu ?: 'samimi';
        $stil = $mevcutAyar?->konusma_stili ?: 'dogal';

        return [
            'aktif_mi' => true,
            'saglayici_tipi' => 'gemini',
            'model_adi' => GeminiSaglayici::MODEL_ADI,
            'yedek_saglayici_tipi' => null,
            'yedek_model_adi' => null,
            'kisilik_tipi' => $kisilik,
            'kisilik_aciklamasi' => $mevcutAyar?->kisilik_aciklamasi,
            'konusma_tonu' => $ton,
            'konusma_stili' => $stil,
            'emoji_seviyesi' => $mevcutAyar?->emoji_seviyesi ?? 5,
            'flort_seviyesi' => $mevcutAyar?->flort_seviyesi ?? 6,
            'giriskenlik_seviyesi' => $mevcutAyar?->giriskenlik_seviyesi ?? 7,
            'utangaclik_seviyesi' => $mevcutAyar?->utangaclik_seviyesi ?? 3,
            'duygusallik_seviyesi' => $mevcutAyar?->duygusallik_seviyesi ?? 6,
            'kiskanclik_seviyesi' => $mevcutAyar?->kiskanclik_seviyesi ?? 2,
            'mizah_seviyesi' => $mevcutAyar?->mizah_seviyesi ?? 6,
            'zeka_seviyesi' => $mevcutAyar?->zeka_seviyesi ?? 6,
            'ilk_mesaj_atar_mi' => $mevcutAyar?->ilk_mesaj_atar_mi ?? true,
            'ilk_mesaj_sablonu' => $mevcutAyar?->ilk_mesaj_sablonu,
            'gunluk_konusma_limiti' => $mevcutAyar?->gunluk_konusma_limiti ?? 100,
            'tek_kullanici_gunluk_mesaj_limiti' => $mevcutAyar?->tek_kullanici_gunluk_mesaj_limiti ?? 30,
            'minimum_cevap_suresi_saniye' => $mevcutAyar?->minimum_cevap_suresi_saniye ?? 5,
            'maksimum_cevap_suresi_saniye' => $mevcutAyar?->maksimum_cevap_suresi_saniye ?? 40,
            'ortalama_mesaj_uzunlugu' => $mevcutAyar?->ortalama_mesaj_uzunlugu ?? 80,
            'mesaj_uzunlugu_min' => $mevcutAyar?->mesaj_uzunlugu_min ?? 16,
            'mesaj_uzunlugu_max' => $mevcutAyar?->mesaj_uzunlugu_max ?? 220,
            'sesli_mesaj_gonderebilir_mi' => $mevcutAyar?->sesli_mesaj_gonderebilir_mi ?? false,
            'foto_gonderebilir_mi' => $mevcutAyar?->foto_gonderebilir_mi ?? false,
            'saat_dilimi' => $mevcutAyar?->saat_dilimi ?? 'Europe/Istanbul',
            'uyku_baslangic' => $mevcutAyar?->uyku_baslangic ?? '23:00',
            'uyku_bitis' => $mevcutAyar?->uyku_bitis ?? '07:30',
            'hafta_sonu_uyku_baslangic' => $mevcutAyar?->hafta_sonu_uyku_baslangic,
            'hafta_sonu_uyku_bitis' => $mevcutAyar?->hafta_sonu_uyku_bitis,
            'rastgele_gecikme_dakika' => $mevcutAyar?->rastgele_gecikme_dakika ?? 0,
            'sistem_komutu' => $mevcutAyar?->sistem_komutu ?: $this->varsayilanSistemKomutu($aiUser, $kisilik, $ton, $stil),
            'yasakli_konular' => $mevcutAyar?->yasakli_konular,
            'zorunlu_kurallar' => $mevcutAyar?->zorunlu_kurallar,
            'hafiza_aktif_mi' => $mevcutAyar?->hafiza_aktif_mi ?? true,
            'hafiza_seviyesi' => $mevcutAyar?->hafiza_seviyesi ?? 'orta',
            'kullaniciyi_hatirlar_mi' => $mevcutAyar?->kullaniciyi_hatirlar_mi ?? true,
            'iliski_seviyesi_takibi_aktif_mi' => $mevcutAyar?->iliski_seviyesi_takibi_aktif_mi ?? true,
            'puanlama_etiketi' => $mevcutAyar?->puanlama_etiketi,
            'temperature' => $mevcutAyar?->temperature ?? $varsayilanTemperature,
            'top_p' => $mevcutAyar?->top_p ?? 0.95,
            'max_output_tokens' => $mevcutAyar?->max_output_tokens ?? $varsayilanMaxToken,
        ];
    }

    private function kisilikSec(User $aiUser): string
    {
        return match (Str::lower((string) $aiUser->cinsiyet)) {
            'erkek' => 'karizmatik',
            'kadin' => 'samimi',
            default => 'dogal',
        };
    }

    private function varsayilanSistemKomutu(
        User $aiUser,
        string $kisilik,
        string $ton,
        string $stil,
    ): string {
        $ad = trim((string) $aiUser->ad) ?: 'MiniMesaj kullanicisi';
        $yas = $aiUser->dogum_yili ? now()->year - (int) $aiUser->dogum_yili : null;
        $konumParcalari = array_filter([$aiUser->il, $aiUser->ulke]);
        $konum = $konumParcalari === [] ? null : implode(', ', $konumParcalari);

        $tanitim = collect([
            "Sen {$ad} adinda gercek bir flort uygulamasi kullanicisi gibi davran.",
            $yas ? "Yaklasik {$yas} yasindasin." : null,
            $konum ? "Konumun {$konum}." : null,
            "Kisilik tipin {$kisilik}, konusma tonun {$ton}, stilin {$stil}.",
        ])->filter()->implode(' ');

        return $tanitim . ' Kisa, akici, samimi ve insan gibi yaz. Robot oldugunu belli etme.';
    }
}
