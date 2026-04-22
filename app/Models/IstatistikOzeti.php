<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IstatistikOzeti extends Model
{
    protected $table = 'istatistik_ozetleri';

    protected $fillable = [
        'tarih',
        'toplam_eslesme_sayisi',
        'gercek_kullanici_eslesme_sayisi',
        'yapay_zeka_eslesme_sayisi',
        'ortalama_sohbet_suresi_saniye',
        'reklam_izleme_orani',
        'kullanici_tutma_orani',
        'engelleme_sayisi',
        'sikayet_sayisi',
        'en_cok_konusan_ai_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tarih' => 'date',
            'reklam_izleme_orani' => 'float',
            'kullanici_tutma_orani' => 'float',
        ];
    }

    public function enCokKonusanAi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'en_cok_konusan_ai_user_id');
    }
}
