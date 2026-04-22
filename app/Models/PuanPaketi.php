<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PuanPaketi extends Model
{
    protected $table = 'puan_paketleri';

    protected $fillable = [
        'kod',
        'android_urun_kodu',
        'ios_urun_kodu',
        'puan',
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
            'fiyat' => 'decimal:2',
            'onerilen_mi' => 'boolean',
            'aktif' => 'boolean',
            'puan' => 'integer',
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
