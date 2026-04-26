<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppLegalDocument extends Model
{
    use SoftDeletes;

    public const TYPE_PRIVACY = 'privacy';
    public const TYPE_KVKK = 'kvkk';
    public const TYPE_TERMS = 'terms';

    public const TYPES = [
        self::TYPE_PRIVACY => 'Gizlilik Politikasi',
        self::TYPE_KVKK => 'KVKK Aydinlatma Metni',
        self::TYPE_TERMS => 'Kullanim Kosullari',
    ];

    protected $fillable = [
        'type',
        'app_language_id',
        'title',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(AppLanguage::class, 'app_language_id');
    }
}
