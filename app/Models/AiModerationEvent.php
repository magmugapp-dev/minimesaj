<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function aiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Sohbet::class, 'conversation_id');
    }
}
