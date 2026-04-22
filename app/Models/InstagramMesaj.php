<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InstagramMesaj extends Model
{
    protected $table = 'instagram_mesajlari';

    protected $fillable = [
        'instagram_hesap_id',
        'instagram_kisi_id',
        'gonderen_tipi',
        'mesaj_metni',
        'mesaj_tipi',
        'ai_cevapladi_mi',
        'gonderildi_mi',
        'instagram_mesaj_kodu',
    ];

    protected function casts(): array
    {
        return [
            'ai_cevapladi_mi' => 'boolean',
            'gonderildi_mi' => 'boolean',
        ];
    }

    public function hesap(): BelongsTo
    {
        return $this->belongsTo(InstagramHesap::class, 'instagram_hesap_id');
    }

    public function kisi(): BelongsTo
    {
        return $this->belongsTo(InstagramKisi::class, 'instagram_kisi_id');
    }

    public function aiGorevi(): HasOne
    {
        return $this->hasOne(InstagramAiGorevi::class, 'instagram_mesaj_id');
    }
}
