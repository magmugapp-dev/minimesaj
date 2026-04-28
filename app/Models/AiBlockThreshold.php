<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiBlockThreshold extends Model
{
    protected $fillable = [
        'category',
        'threshold',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
