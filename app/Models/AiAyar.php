<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAyar extends Model
{
    protected $casts = [
        'yasakli_konular' => 'array',
        'zorunlu_kurallar' => 'array',
    ];
    protected $table = 'ai_ayarlar';

    protected $fillable = [
        'user_id',
        'aktif_mi',
        'saglayici_tipi',
        'model_adi',
        'yedek_saglayici_tipi',
        'yedek_model_adi',
        'kisilik_tipi',
        'kisilik_aciklamasi',
        'konusma_tonu',
        'konusma_stili',
        'emoji_seviyesi',
        'flort_seviyesi',
        'giriskenlik_seviyesi',
        'utangaclik_seviyesi',
        'duygusallik_seviyesi',
        'kiskanclik_seviyesi',
        'mizah_seviyesi',
        'zeka_seviyesi',
        'ilk_mesaj_atar_mi',
        'ilk_mesaj_sablonu',
        'gunluk_konusma_limiti',
        'tek_kullanici_gunluk_mesaj_limiti',
        'minimum_cevap_suresi_saniye',
        'maksimum_cevap_suresi_saniye',
        'ortalama_mesaj_uzunlugu',
        'mesaj_uzunlugu_min',
        'mesaj_uzunlugu_max',
        'sesli_mesaj_gonderebilir_mi',
        'foto_gonderebilir_mi',
        'saat_dilimi',
        'uyku_baslangic',
        'uyku_bitis',
        'hafta_sonu_uyku_baslangic',
        'hafta_sonu_uyku_bitis',
        'rastgele_gecikme_dakika',
        'sistem_komutu',
        'yasakli_konular',
        'zorunlu_kurallar',
        'hafiza_aktif_mi',
        'hafiza_seviyesi',
        'kullaniciyi_hatirlar_mi',
        'iliski_seviyesi_takibi_aktif_mi',
        'puanlama_etiketi',
        'temperature',
        'top_p',
        'max_output_tokens',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
