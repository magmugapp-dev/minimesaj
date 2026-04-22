<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFotografi extends Model
{
    protected $table = 'user_fotograflari';

    protected $fillable = [
        'user_id',
        'dosya_yolu',
        'onizleme_yolu',
        'medya_tipi',
        'mime_tipi',
        'sure_saniye',
        'sira_no',
        'ana_fotograf_mi',
        'aktif_mi',
    ];

    protected function casts(): array
    {
        return [
            'sure_saniye' => 'integer',
            'ana_fotograf_mi' => 'boolean',
            'aktif_mi' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
