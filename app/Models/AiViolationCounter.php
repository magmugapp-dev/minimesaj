<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiViolationCounter extends Model
{
    protected $fillable = [
        'ai_user_id',
        'user_id',
        'category',
        'count',
        'last_violation_at',
        'blocked',
    ];

    protected function casts(): array
    {
        return [
            'last_violation_at' => 'datetime',
            'blocked' => 'boolean',
        ];
    }
}
