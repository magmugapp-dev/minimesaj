<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuanHareketi extends Model
{
    protected $table = 'puan_hareketleri';

    protected $fillable = [
        'user_id',
        'islem_tipi',
        'puan_miktari',
        'onceki_bakiye',
        'sonraki_bakiye',
        'aciklama',
        'referans_tipi',
        'referans_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
