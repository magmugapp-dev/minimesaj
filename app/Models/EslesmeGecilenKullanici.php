<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EslesmeGecilenKullanici extends Model
{
    protected $table = 'eslesme_gecilen_kullanicilar';

    protected $fillable = [
        'gecen_user_id',
        'gecilen_user_id',
    ];

    public function gecen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gecen_user_id');
    }

    public function gecilen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gecilen_user_id');
    }
}
