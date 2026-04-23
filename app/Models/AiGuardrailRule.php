<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGuardrailRule extends Model
{
    protected $fillable = [
        'ai_engine_config_id',
        'ai_persona_profile_id',
        'kanal',
        'rule_type',
        'etiket',
        'icerik',
        'severity',
        'aktif_mi',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function engineConfig(): BelongsTo
    {
        return $this->belongsTo(AiEngineConfig::class, 'ai_engine_config_id');
    }

    public function personaProfile(): BelongsTo
    {
        return $this->belongsTo(AiPersonaProfile::class, 'ai_persona_profile_id');
    }
}
