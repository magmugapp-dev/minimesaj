<?php

namespace App\Services\Ai;

use App\Models\GeminiApiKey;

class GeminiKeyPool
{
    public static function pick(): ?GeminiApiKey
    {
        return GeminiApiKey::query()
            ->where('active', true)
            ->where(function ($query): void {
                $query->whereNull('exhausted_until')
                    ->orWhere('exhausted_until', '<=', now());
            })
            ->orderByDesc('priority')
            ->orderBy('last_used_at')
            ->first();
    }

    public static function markExhausted(GeminiApiKey $key, int $seconds = 60): void
    {
        $key->forceFill(['exhausted_until' => now()->addSeconds($seconds)])->save();
    }

    public static function recordUse(GeminiApiKey $key): void
    {
        $key->forceFill([
            'total_requests' => ((int) $key->total_requests) + 1,
            'last_used_at' => now(),
        ])->save();
    }
}
