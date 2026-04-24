<?php

namespace App\Services;

use App\Events\SohbetTypingUpdated;
use App\Models\Sohbet;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SohbetTypingService
{
    private const TTL_SECONDS = 6;

    public function setTyping(Sohbet $conversation, User $user, bool $typing): void
    {
        if ($typing) {
            Cache::put($this->key($conversation, $user), true, now()->addSeconds(self::TTL_SECONDS));
        } else {
            Cache::forget($this->key($conversation, $user));
        }

        SohbetTypingUpdated::dispatch(
            $conversation->id,
            $user->id,
            $typing,
            $typing ? 'Yaziyor...' : null,
        );
    }

    private function key(Sohbet $conversation, User $user): string
    {
        return sprintf('sohbet:%d:typing:%d', $conversation->id, $user->id);
    }
}
