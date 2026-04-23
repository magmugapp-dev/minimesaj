<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMemory extends Model
{
    public const TIP_FACT = 'fact';
    public const TIP_PREFERENCE = 'preference';
    public const TIP_EMOTION = 'emotion';
    public const TIP_RELATIONSHIP = 'relationship';
    public const TIP_BOUNDARY = 'boundary';
    public const TIP_SUMMARY = 'summary';

    protected $fillable = [
        'ai_user_id',
        'kanal',
        'hedef_tipi',
        'hedef_id',
        'hafiza_tipi',
        'anahtar',
        'icerik',
        'deger',
        'normalize_deger',
        'guven_skoru',
        'gecerlilik_tipi',
        'ilk_goruldu_at',
        'son_goruldu_at',
        'onem_puani',
        'kaynak_mesaj_id',
        'kaynak_instagram_mesaj_id',
        'son_kullanildi_at',
        'son_kullanma_tarihi',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'guven_skoru' => 'float',
            'ilk_goruldu_at' => 'datetime',
            'son_goruldu_at' => 'datetime',
            'son_kullanildi_at' => 'datetime',
            'son_kullanma_tarihi' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function scopeForCounterpart(
        Builder $query,
        int $aiUserId,
        string $hedefTipi,
        int $hedefId,
        ?string $kanal = null,
    ): Builder {
        return $query
            ->where('ai_user_id', $aiUserId)
            ->where('hedef_tipi', $hedefTipi)
            ->where('hedef_id', $hedefId)
            ->when($kanal !== null, fn (Builder $builder) => $builder->where('kanal', $kanal));
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('son_kullanma_tarihi')
                ->orWhere('son_kullanma_tarihi', '>', now());
        });
    }

    public function aiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_user_id');
    }
}
