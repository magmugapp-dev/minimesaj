<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAvailabilitySchedule extends Model
{
    protected $fillable = [
        'user_id',
        'recurrence_type',
        'specific_date',
        'day_of_week',
        'starts_at',
        'ends_at',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'specific_date' => 'date',
            'day_of_week' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
