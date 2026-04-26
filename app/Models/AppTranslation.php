<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppTranslation extends Model
{
    protected $fillable = [
        'app_translation_key_id',
        'app_language_id',
        'value',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function translationKey(): BelongsTo
    {
        return $this->belongsTo(AppTranslationKey::class, 'app_translation_key_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(AppLanguage::class, 'app_language_id');
    }
}
