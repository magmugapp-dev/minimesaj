<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceBinding extends Model
{
    protected $fillable = [
        'device_fingerprint',
        'user_id',
        'platform',
        'banned',
        'banned_at',
        'bound_at',
    ];

    protected function casts(): array
    {
        return [
            'banned' => 'boolean',
            'banned_at' => 'datetime',
            'bound_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
