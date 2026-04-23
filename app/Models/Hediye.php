<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hediye extends Model
{
    protected $table = 'hediyeler';

    protected $fillable = [
        'kod',
        'ad',
        'ikon',
        'puan_bedeli',
        'aktif',
        'sira',
    ];

    protected function casts(): array
    {
        return [
            'puan_bedeli' => 'integer',
            'aktif' => 'boolean',
            'sira' => 'integer',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function gonderimler(): HasMany
    {
        return $this->hasMany(HediyeGonderimi::class, 'hediye_id');
    }
}
