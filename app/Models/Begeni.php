<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Begeni extends Model
{
    protected $table = 'begeniler';

    protected $fillable = [
        'begenen_user_id',
        'begenilen_user_id',
        'eslesmeye_donustu_mu',
        'goruldu_mu',
    ];

    protected function casts(): array
    {
        return [
            'eslesmeye_donustu_mu' => 'boolean',
            'goruldu_mu' => 'boolean',
        ];
    }

    public function begenen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'begenen_user_id');
    }

    public function begenilen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'begenilen_user_id');
    }
}
