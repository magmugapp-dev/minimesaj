<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiPersonaProfile extends Model
{
    protected $fillable = [
        'ai_user_id',
        'ai_engine_config_id',
        'aktif_mi',
        'dating_aktif_mi',
        'instagram_aktif_mi',
        'ilk_mesaj_atar_mi',
        'ilk_mesaj_tonu',
        'persona_ozeti',
        'ana_dil_kodu',
        'ana_dil_adi',
        'ikinci_diller',
        'persona_ulke',
        'persona_bolge',
        'persona_sehir',
        'persona_mahalle',
        'kulturel_koken',
        'uyruk',
        'yasam_tarzi',
        'meslek',
        'sektor',
        'egitim',
        'okul_bolum',
        'yas_araligi',
        'gunluk_rutin',
        'hobiler',
        'sevdigi_mekanlar',
        'aile_arkadas_notu',
        'iliski_gecmisi_tonu',
        'konusma_imzasi',
        'argo_seviyesi',
        'sicaklik_seviyesi',
        'empati_seviyesi',
        'merak_seviyesi',
        'ozguven_seviyesi',
        'sabir_seviyesi',
        'baskinlik_seviyesi',
        'sarkastiklik_seviyesi',
        'romantizm_seviyesi',
        'oyunculuk_seviyesi',
        'ciddiyet_seviyesi',
        'gizem_seviyesi',
        'hassasiyet_seviyesi',
        'enerji_seviyesi',
        'kiskanclik_seviyesi',
        'zeka_seviyesi',
        'cevap_ritmi',
        'emoji_aliskanligi',
        'kacinilacak_persona_detaylari',
        'konusma_tonu',
        'konusma_stili',
        'mizah_seviyesi',
        'flort_seviyesi',
        'emoji_seviyesi',
        'giriskenlik_seviyesi',
        'utangaclik_seviyesi',
        'duygusallik_seviyesi',
        'iyimserlik_seviyesi',
        'yaraticilik_seviyesi',
        'detaycilik_seviyesi',
        'sosyallik_seviyesi',
        'disiplin_seviyesi',
        'duzgunluk_seviyesi',
        'liderlik_seviyesi',
        'mesaj_uzunlugu_min',
        'mesaj_uzunlugu_max',
        'minimum_cevap_suresi_saniye',
        'maksimum_cevap_suresi_saniye',
        'saat_dilimi',
        'uyku_baslangic',
        'uyku_bitis',
        'hafta_sonu_uyku_baslangic',
        'hafta_sonu_uyku_bitis',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'dating_aktif_mi' => 'boolean',
            'instagram_aktif_mi' => 'boolean',
            'ilk_mesaj_atar_mi' => 'boolean',
            'ikinci_diller' => 'array',
            'metadata' => 'array',
        ];
    }

    public function aiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }

    public function engineConfig(): BelongsTo
    {
        return $this->belongsTo(AiEngineConfig::class, 'ai_engine_config_id');
    }

    public function guardrailRules(): HasMany
    {
        return $this->hasMany(AiGuardrailRule::class);
    }
}
