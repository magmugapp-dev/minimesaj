<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppFaqItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'app_language_id',
        'question',
        'answer',
        'category',
        'screen',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(AppLanguage::class, 'app_language_id');
    }
}
