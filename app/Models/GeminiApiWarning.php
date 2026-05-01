<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeminiApiWarning extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ai_user_id',
        'error_code',
        'error_message',
        'turn_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function aiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(AiMessageTurn::class, 'turn_id');
    }
}
