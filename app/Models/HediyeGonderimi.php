<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HediyeGonderimi extends Model
{
    protected $table = 'hediye_gonderimleri';

    protected $fillable = [
        'gonderen_user_id',
        'alici_user_id',
        'hediye_id',
        'hediye_adi',
        'puan_bedeli',
    ];

    protected $appends = [
        'hediye_tipi',
        'puan_degeri',
    ];

    public function gonderen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gonderen_user_id');
    }

    public function alici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alici_user_id');
    }

    public function hediye(): BelongsTo
    {
        return $this->belongsTo(Hediye::class, 'hediye_id');
    }

    public function getHediyeTipiAttribute(): string
    {
        return (string) $this->hediye_adi;
    }

    public function getPuanDegeriAttribute(): int
    {
        return (int) $this->puan_bedeli;
    }
}
