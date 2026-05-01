<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModerationEvent extends Model
{
    protected $fillable = [
        'ai_user_id',
        'user_id',
        'conversation_id',
        'event_type',
        'dominance',
        'lockout_until',
        'resolved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'lockout_until' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
