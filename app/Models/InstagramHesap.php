<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstagramHesap extends Model
{
    use HasFactory;

    protected $table = 'instagram_hesaplari';

    protected $fillable = [
        'user_id',
        'instagram_kullanici_adi',
        'instagram_profil_id',
        'otomatik_cevap_aktif_mi',
        'yarim_otomatik_mod_aktif_mi',
        'son_baglanti_tarihi',
        'aktif_mi',
    ];

    protected function casts(): array
    {
        return [
            'otomatik_cevap_aktif_mi' => 'boolean',
            'yarim_otomatik_mod_aktif_mi' => 'boolean',
            'aktif_mi' => 'boolean',
            'son_baglanti_tarihi' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kisiler(): HasMany
    {
        return $this->hasMany(InstagramKisi::class, 'instagram_hesap_id');
    }

    public function mesajlar(): HasMany
    {
        return $this->hasMany(InstagramMesaj::class, 'instagram_hesap_id');
    }

    public function aiGorevleri(): HasMany
    {
        return $this->hasMany(InstagramAiGorevi::class, 'instagram_hesap_id');
    }
}
