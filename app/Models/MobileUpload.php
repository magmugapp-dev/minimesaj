<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileUpload extends Model
{
    protected $fillable = [
        'user_id',
        'client_upload_id',
        'mesaj_tipi',
        'dosya_yolu',
        'mime_tipi',
        'boyut',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
