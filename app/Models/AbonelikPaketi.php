<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AbonelikPaketi extends Model
{
    protected $table = 'abonelik_paketleri';

    protected $fillable = [
        'kod',
        'android_urun_kodu',
        'ios_urun_kodu',
        'sure_ay',
        'fiyat',
        'para_birimi',
        'rozet',
        'onerilen_mi',
        'aktif',
        'sira',
    ];

    protected function casts(): array
    {
        return [
            'sure_ay' => 'integer',
            'fiyat' => 'decimal:2',
            'onerilen_mi' => 'boolean',
            'aktif' => 'boolean',
            'sira' => 'integer',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function magazaUrunKodu(string $platform): ?string
    {
        return match ($platform) {
            'ios' => $this->ios_urun_kodu,
            'android' => $this->android_urun_kodu,
            default => null,
        };
    }
}
