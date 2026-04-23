<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConversationState extends Model
{
    public const DURUM_IDLE = 'idle';
    public const DURUM_TYPING = 'typing';
    public const DURUM_QUEUED = 'queued';
    public const DURUM_PAUSED = 'paused';
    public const DURUM_BLOCKED = 'blocked';

    protected $fillable = [
        'ai_user_id',
        'kanal',
        'hedef_tipi',
        'hedef_id',
        'samimiyet_puani',
        'ilgi_puani',
        'guven_puani',
        'enerji_puani',
        'ruh_hali',
        'gerilim_seviyesi',
        'son_konu',
        'son_kullanici_duygusu',
        'son_ai_niyeti',
        'son_ozet',
        'ai_durumu',
        'son_mesaj_at',
        'son_ai_mesaj_at',
        'planlanan_cevap_at',
        'durum_guncellendi_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'son_mesaj_at' => 'datetime',
            'son_ai_mesaj_at' => 'datetime',
            'planlanan_cevap_at' => 'datetime',
            'durum_guncellendi_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function scopeForCounterpart(
        Builder $query,
        int $aiUserId,
        string $kanal,
        string $hedefTipi,
        int $hedefId,
    ): Builder {
        return $query
            ->where('ai_user_id', $aiUserId)
            ->where('kanal', $kanal)
            ->where('hedef_tipi', $hedefTipi)
            ->where('hedef_id', $hedefId);
    }

    public function aiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }
}
