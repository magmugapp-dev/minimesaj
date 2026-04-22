<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DestekTalebi extends Model
{
    protected $table = 'destek_talepleri';

    protected $fillable = [
        'user_id',
        'mesaj',
        'durum',
        'yonetici_notu',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function yanitlar(): HasMany
    {
        return $this->hasMany(DestekTalebiYaniti::class)->latest();
    }
}
