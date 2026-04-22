<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Odeme extends Model
{
    protected $table = 'odemeler';

    protected $fillable = [
        'user_id',
        'platform',
        'magaza_tipi',
        'urun_kodu',
        'urun_tipi',
        'islem_kodu',
        'tutar',
        'para_birimi',
        'durum',
        'dogrulama_durumu',
    ];

    public static function platformMagazaTipi(string $platform): string
    {
        return match ($platform) {
            'ios' => 'app_store',
            'android' => 'google_play',
        };
    }

    protected function casts(): array
    {
        return [
            'tutar' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
