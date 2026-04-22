<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YapayZekaGorevi extends Model
{
    protected $table = 'yapay_zeka_gorevleri';

    protected $fillable = [
        'sohbet_id',
        'gelen_mesaj_id',
        'ai_user_id',
        'durum',
        'deneme_sayisi',
        'hata_mesaji',
        'cevap_metni',
        'saglayici_tipi',
        'model_adi',
        'giris_token_sayisi',
        'cikis_token_sayisi',
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

    public function sohbet(): BelongsTo
    {
        return $this->belongsTo(Sohbet::class, 'sohbet_id');
    }

    public function gelenMesaj(): BelongsTo
    {
        return $this->belongsTo(Mesaj::class, 'gelen_mesaj_id');
    }

    public function aiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }
}
