<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Eslesme extends Model
{
    protected $table = 'eslesmeler';

    protected $fillable = [
        'user_id',
        'eslesen_user_id',
        'eslesme_turu',
        'eslesme_kaynagi',
        'durum',
        'tekrar_eslesebilir_mi',
        'baslatan_user_id',
        'bitis_sebebi',
    ];

    protected function casts(): array
    {
        return [
            'tekrar_eslesebilir_mi' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function eslesenUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eslesen_user_id');
    }

    public function baslatanUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'baslatan_user_id');
    }

    public function sohbet(): HasOne
    {
        return $this->hasOne(Sohbet::class, 'eslesme_id');
    }
}
