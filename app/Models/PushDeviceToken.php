<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDeviceToken extends Model
{
    protected $table = 'push_device_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'cihaz_adi',
        'uygulama_versiyonu',
        'dil',
        'bildirim_izni',
        'son_gorulme_at',
    ];

    protected function casts(): array
    {
        return [
            'bildirim_izni' => 'boolean',
            'son_gorulme_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
