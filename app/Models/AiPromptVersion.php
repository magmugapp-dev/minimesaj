<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPromptVersion extends Model
{
    protected $fillable = [
        'version',
        'hash',
        'prompt_xml',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
