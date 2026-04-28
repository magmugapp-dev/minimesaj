<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCharacter extends Model
{
    protected $fillable = [
        'user_id',
        'character_id',
        'character_version',
        'schema_version',
        'active',
        'display_name',
        'username',
        'primary_language_code',
        'primary_language_name',
        'city',
        'quality_tag',
        'character_json',
        'model_name',
        'temperature',
        'top_p',
        'max_output_tokens',
        'reengagement_active',
        'reengagement_after_hours',
        'reengagement_daily_limit',
        'reengagement_templates',
        'last_reengagement_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'character_json' => 'array',
            'temperature' => 'float',
            'top_p' => 'float',
            'reengagement_active' => 'boolean',
            'reengagement_templates' => 'array',
            'last_reengagement_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
