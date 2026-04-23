<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstagramKisi extends Model
{
    use HasFactory;

    protected $table = 'instagram_kisileri';

    protected $fillable = [
        'instagram_hesap_id',
        'instagram_kisi_id',
        'kullanici_adi',
        'gorunen_ad',
        'profil_resmi',
        'notlar',
        'son_mesaj_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'son_mesaj_tarihi' => 'datetime',
        ];
    }

    public function hesap(): BelongsTo
    {
        return $this->belongsTo(InstagramHesap::class, 'instagram_hesap_id');
    }

    public function mesajlar(): HasMany
    {
        return $this->hasMany(InstagramMesaj::class, 'instagram_kisi_id');
    }
}
