<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeminiApiKey extends Model
{
    protected $fillable = [
        'label',
        'api_key',
        'active',
        'priority',
        'exhausted_until',
        'total_requests',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'active' => 'boolean',
            'exhausted_until' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
