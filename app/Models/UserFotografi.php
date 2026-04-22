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
        'sira_no',
        'ana_fotograf_mi',
        'aktif_mi',
    ];

    protected function casts(): array
    {
        return [
            'ana_fotograf_mi' => 'boolean',
            'aktif_mi' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
