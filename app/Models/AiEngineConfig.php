<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiEngineConfig extends Model
{
    protected $fillable = [
        'ad',
        'saglayici_tipi',
        'model_adi',
        'aktif_mi',
        'temperature',
        'top_p',
        'max_output_tokens',
        'sistem_komutu',
        'guardrail_modu',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'temperature' => 'float',
            'top_p' => 'float',
            'metadata' => 'array',
        ];
    }

    public function personaProfiles(): HasMany
    {
        return $this->hasMany(AiPersonaProfile::class);
    }

    public function guardrailRules(): HasMany
    {
        return $this->hasMany(AiGuardrailRule::class);
    }
}
