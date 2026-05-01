<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
