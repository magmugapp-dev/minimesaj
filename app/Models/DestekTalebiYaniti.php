<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestekTalebiYaniti extends Model
{
    protected $table = 'destek_talebi_yanitlari';

    protected $fillable = [
        'destek_talebi_id',
        'admin_user_id',
        'mesaj',
    ];

    public function destekTalebi(): BelongsTo
    {
        return $this->belongsTo(DestekTalebi::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
