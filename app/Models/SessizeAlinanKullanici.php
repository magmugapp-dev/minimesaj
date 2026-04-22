<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessizeAlinanKullanici extends Model
{
    protected $table = 'sessize_alinan_kullanicilar';

    protected $fillable = [
        'user_id',
        'sessize_alinan_user_id',
        'sessiz_bitis_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'sessiz_bitis_tarihi' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sessizeAlinan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sessize_alinan_user_id');
    }

    public function aktifMi(): bool
    {
        return $this->sessiz_bitis_tarihi === null || $this->sessiz_bitis_tarihi->isFuture();
    }

    public static function aktifKayitVarMi(int $userId, int $sessizeAlinanUserId): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('sessize_alinan_user_id', $sessizeAlinanUserId)
            ->where(function ($query) {
                $query->whereNull('sessiz_bitis_tarihi')
                    ->orWhere('sessiz_bitis_tarihi', '>', now());
            })
            ->exists();
    }
}
