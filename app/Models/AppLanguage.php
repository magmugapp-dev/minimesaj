<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppLanguage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(AppTranslation::class);
    }

    public function legalDocuments(): HasMany
    {
        return $this->hasMany(AppLegalDocument::class);
    }

    public function faqItems(): HasMany
    {
        return $this->hasMany(AppFaqItem::class);
    }
}
