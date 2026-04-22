<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramAiGorevi extends Model
{
    protected $table = 'instagram_ai_gorevleri';

    protected $fillable = [
        'instagram_mesaj_id',
        'instagram_hesap_id',
        'instagram_kisi_id',
        'durum',
        'deneme_sayisi',
        'hata_mesaji',
        'cevap_metni',
        'saglayici_tipi',
        'model_adi',
        'istek_baslatildi_at',
        'son_parca_at',
        'tamamlandi_at',
        'yanit_suresi_ms',
    ];

    protected function casts(): array
    {
        return [
            'istek_baslatildi_at' => 'datetime',
            'son_parca_at' => 'datetime',
            'tamamlandi_at' => 'datetime',
        ];
    }

    public function mesaj(): BelongsTo
    {
        return $this->belongsTo(InstagramMesaj::class, 'instagram_mesaj_id');
    }

    public function hesap(): BelongsTo
    {
        return $this->belongsTo(InstagramHesap::class, 'instagram_hesap_id');
    }

    public function kisi(): BelongsTo
    {
        return $this->belongsTo(InstagramKisi::class, 'instagram_kisi_id');
    }
}
