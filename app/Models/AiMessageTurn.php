<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessageTurn extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DEFERRED = 'deferred';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'conversation_id',
        'ai_user_id',
        'source_message_id',
        'turn_type',
        'status',
        'planned_at',
        'retry_after',
        'attempt_count',
        'max_attempts',
        'idempotency_key',
        'delivered_message_ids',
        'reminder_sent_at',
        'started_at',
        'completed_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'planned_at' => 'datetime',
            'retry_after' => 'datetime',
            'delivered_message_ids' => 'array',
            'reminder_sent_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Sohbet::class, 'conversation_id');
    }

    public function aiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(Mesaj::class, 'source_message_id');
    }
}
