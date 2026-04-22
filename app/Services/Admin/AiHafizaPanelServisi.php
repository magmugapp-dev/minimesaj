<?php

namespace App\Services\Admin;

use App\Models\AiHafiza;
use App\Models\Eslesme;
use App\Models\InstagramHesap;
use App\Models\InstagramKisi;
use App\Models\User;
use Illuminate\Support\Collection;

class AiHafizaPanelServisi
{
    private const HAFIZA_TIPI_ETIKETLERI = [
        AiHafiza::HAFIZA_TIPI_BILGI => 'Bilgiler',
        AiHafiza::HAFIZA_TIPI_TERCIH => 'Tercihler',
        AiHafiza::HAFIZA_TIPI_SINIR => 'Sinirlar',
        AiHafiza::HAFIZA_TIPI_DUYGU => 'Duygular',
    ];

    private const KONU_ETIKETLERI = [
        'ad_soyad' => 'Ad / Soyad',
        'memleket' => 'Memleket',
        'yasadigi_sehir' => 'Yasadigi Sehir',
        'meslek' => 'Meslek',
        'egitim_lise_durum' => 'Lise Durumu',
        'egitim_universite_adi' => 'Universite',
        'egitim_universite_bolum' => 'Universite Bolumu',
        'egitim_universite_durum' => 'Universite Durumu',
        'tercih_genel' => 'Genel Tercih',
        'tercih_yemek' => 'Yemek Tercihi',
        'tercih_icecek' => 'Icecek Tercihi',
        'tercih_hobi' => 'Hobi / Aktivite',
        'tercih_iletisim' => 'Iletisim Tercihi',
        'tercih_bulusma' => 'Bulusma Tercihi',
        'sinir_genel' => 'Genel Sinir',
        'sinir_konu' => 'Konusalmaz Konu',
        'sinir_iletisim' => 'Iletisim Siniri',
        'sinir_iliski' => 'Iliski Siniri',
        'duygu_genel' => 'Genel Duygu',
        'duygu_iliskiye_karsi' => 'Iliskiye Karsi Duygu',
        'duygu_gunluk_stres' => 'Gunluk Stres',
    ];

    public function instagramKisiPaneli(InstagramHesap $instagramHesap, InstagramKisi $instagramKisi): ?array
    {
        return $this->hafizaPaneliOlustur(
            aiUserId: (int) $instagramHesap->user_id,
            hedefTipi: AiHafiza::HEDEF_TIPI_INSTAGRAM_KISI,
            hedefId: (int) $instagramKisi->id,
            baslik: $this->instagramKisiEtiketi($instagramKisi) . ' icin AI hafizasi',
            aciklama: '@' . $this->instagramKullaniciAdi($instagramKisi) . ' hakkinda biriken aktif hafiza kayitlari.',
            aiEtiketi: $this->kullaniciEtiketi($instagramHesap->user),
            hedefEtiketi: $this->instagramKisiEtiketi($instagramKisi),
        );
    }

    public function eslesmeKisiPanelleri(Eslesme $eslesme, User $hedefKullanici): array
    {
        $paneller = [];

        foreach ($this->karsiAiKullanicilari($eslesme, $hedefKullanici) as $aiKullanici) {
            $panel = $this->hafizaPaneliOlustur(
                aiUserId: (int) $aiKullanici->id,
                hedefTipi: AiHafiza::HEDEF_TIPI_USER,
                hedefId: (int) $hedefKullanici->id,
                baslik: $this->kullaniciEtiketi($aiKullanici) . ' bu kisi hakkinda ne hatirliyor?',
                aciklama: $this->kullaniciEtiketi($hedefKullanici) . ' icin aktif hafiza kayitlari.',
                aiEtiketi: $this->kullaniciEtiketi($aiKullanici),
                hedefEtiketi: $this->kullaniciEtiketi($hedefKullanici),
            );

            if ($panel !== null) {
                $paneller[] = $panel;
            }
        }

        return $paneller;
    }

    public function eslesmeHafizaOzetleri(Eslesme $eslesme): array
    {
        $ozetler = [];

        foreach (collect([$eslesme->user, $eslesme->eslesenUser])->filter() as $hedefKullanici) {
            $paneller = $this->eslesmeKisiPanelleri($eslesme, $hedefKullanici);

            if ($paneller === []) {
                continue;
            }

            $ozetler[$hedefKullanici->id] = [
                'hedef_kullanici' => $hedefKullanici,
                'hedef_etiketi' => $this->kullaniciEtiketi($hedefKullanici),
                'paneller' => $paneller,
            ];
        }

        return $ozetler;
    }

    private function karsiAiKullanicilari(Eslesme $eslesme, User $hedefKullanici): Collection
    {
        return collect([$eslesme->user, $eslesme->eslesenUser])
            ->filter(fn ($kullanici) => $kullanici instanceof User
                && $kullanici->hesap_tipi === 'ai'
                && ! $kullanici->is($hedefKullanici))
            ->values();
    }

    private function hafizaPaneliOlustur(
        int $aiUserId,
        string $hedefTipi,
        int $hedefId,
        string $baslik,
        string $aciklama,
        ?string $aiEtiketi,
        string $hedefEtiketi,
    ): ?array {
        $hafizalar = AiHafiza::query()
            ->where('ai_user_id', $aiUserId)
            ->hedef($hedefTipi, $hedefId)
            ->aktif()
            ->orderByDesc('onem_puani')
            ->orderByDesc('updated_at')
            ->get();

        if ($hafizalar->isEmpty()) {
            return null;
        }

        $gruplar = [];

        foreach (array_keys(self::HAFIZA_TIPI_ETIKETLERI) as $hafizaTipi) {
            $grupHafizalari = $hafizalar
                ->where('hafiza_tipi', $hafizaTipi)
                ->values();

            if ($grupHafizalari->isEmpty()) {
                continue;
            }

            $gruplar[] = [
                'anahtar' => $hafizaTipi,
                'etiket' => self::HAFIZA_TIPI_ETIKETLERI[$hafizaTipi],
                'kayitlar' => $grupHafizalari
                    ->map(fn (AiHafiza $hafiza) => $this->hafizaKaydi($hafiza))
                    ->all(),
            ];
        }

        $sonGuncelleme = $hafizalar
            ->sortByDesc('updated_at')
            ->first()?->updated_at;

        return [
            'baslik' => $baslik,
            'aciklama' => $aciklama,
            'ai_etiketi' => $aiEtiketi,
            'hedef_etiketi' => $hedefEtiketi,
            'toplam_kayit' => $hafizalar->count(),
            'son_guncelleme_formatli' => $sonGuncelleme?->format('d.m.Y H:i'),
            'gruplar' => $gruplar,
        ];
    }

    private function hafizaKaydi(AiHafiza $hafiza): array
    {
        return [
            'konu_anahtari' => $hafiza->konu_anahtari,
            'konu_etiketi' => $this->konuEtiketi($hafiza->konu_anahtari),
            'icerik' => $hafiza->icerik,
            'onem_puani' => (int) $hafiza->onem_puani,
            'guncellendi_formatli' => $hafiza->updated_at?->format('d.m.Y H:i'),
            'son_kullanma_formatli' => $hafiza->son_kullanma_tarihi?->format('d.m.Y H:i'),
            'duygu_mu' => $hafiza->hafiza_tipi === AiHafiza::HAFIZA_TIPI_DUYGU,
        ];
    }

    private function konuEtiketi(string $konuAnahtari): string
    {
        return self::KONU_ETIKETLERI[$konuAnahtari]
            ?? str($konuAnahtari)->replace('_', ' ')->title()->toString();
    }

    private function kullaniciEtiketi(?User $kullanici): string
    {
        if (! $kullanici) {
            return 'Bilinmeyen kullanici';
        }

        $adSoyad = trim(($kullanici->ad ?? '') . ' ' . ($kullanici->soyad ?? ''));

        return $adSoyad !== '' ? $adSoyad : ('@' . $kullanici->kullanici_adi);
    }

    private function instagramKisiEtiketi(InstagramKisi $instagramKisi): string
    {
        return $instagramKisi->gorunen_ad ?: ('@' . $this->instagramKullaniciAdi($instagramKisi));
    }

    private function instagramKullaniciAdi(InstagramKisi $instagramKisi): string
    {
        return $instagramKisi->instagram_kullanici_adi
            ?? $instagramKisi->kullanici_adi
            ?? 'bilinmeyen-kisi';
    }
}
