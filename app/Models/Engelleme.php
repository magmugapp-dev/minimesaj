<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Engelleme extends Model
{
    protected $table = 'engellemeler';

    protected $fillable = [
        'engelleyen_user_id',
        'engellenen_user_id',
        'sebep',
    ];

    public function engelleyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engelleyen_user_id');
    }

    public function engellenen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engellenen_user_id');
    }
}
