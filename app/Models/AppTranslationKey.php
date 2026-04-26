<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppTranslationKey extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'default_value',
        'category',
        'screen',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(AppTranslation::class);
    }
}
