<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mesaj extends Model
{
    protected $table = 'mesajlar';

    protected $fillable = [
        'sohbet_id',
        'gonderen_user_id',
        'mesaj_tipi',
        'mesaj_metni',
        'dosya_yolu',
        'dosya_suresi',
        'dosya_boyutu',
        'okundu_mu',
        'silindi_mi',
        'herkesten_silindi_mi',
        'ai_tarafindan_uretildi_mi',
        'cevaplanan_mesaj_id',
    ];

    protected function casts(): array
    {
        return [
            'okundu_mu' => 'boolean',
            'silindi_mi' => 'boolean',
            'herkesten_silindi_mi' => 'boolean',
            'ai_tarafindan_uretildi_mi' => 'boolean',
        ];
    }

    public function sohbet(): BelongsTo
    {
        return $this->belongsTo(Sohbet::class, 'sohbet_id');
    }

    public function gonderen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gonderen_user_id');
    }

    public function cevaplananMesaj(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cevaplanan_mesaj_id');
    }
}
