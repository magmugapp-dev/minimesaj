<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReklamOdulu extends Model
{
    protected $table = 'reklam_odulleri';

    protected $fillable = [
        'user_id',
        'olay_kodu',
        'reklam_platformu',
        'reklam_birim_kodu',
        'reklam_tipi',
        'odul_tipi',
        'odul_miktari',
        'dogrulandi_mi',
    ];

    protected function casts(): array
    {
        return [
            'dogrulandi_mi' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
