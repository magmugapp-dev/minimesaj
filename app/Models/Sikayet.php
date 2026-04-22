<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sikayet extends Model
{
    public const HEDEF_TIPI_USER = 'user';
    public const HEDEF_TIPI_MESAJ = 'mesaj';

    public const DURUM_BEKLIYOR = 'bekliyor';
    public const DURUM_INCELENIYOR = 'inceleniyor';
    public const DURUM_COZULDU = 'cozuldu';
    public const DURUM_REDDEDILDI = 'reddedildi';

    protected $table = 'sikayetler';

    protected $fillable = [
        'sikayet_eden_user_id',
        'hedef_tipi',
        'hedef_id',
        'kategori',
        'aciklama',
        'durum',
        'yonetici_notu',
    ];

    public function sikayetEden(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sikayet_eden_user_id');
    }

    public function hedefUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hedef_id');
    }

    public function hedefMesaj(): BelongsTo
    {
        return $this->belongsTo(Mesaj::class, 'hedef_id');
    }

    public function sikayetEdilen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hedef_id');
    }
}
