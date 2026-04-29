<?php

namespace App\Support;

use App\Models\Mesaj;

class MessageMediaUrl
{
    public static function forMessage(Mesaj $message): ?string
    {
        if (! $message->id || trim((string) $message->dosya_yolu) === '') {
            return null;
        }

        return route('mobile.messages.media', ['message' => $message->id]);
    }
}
