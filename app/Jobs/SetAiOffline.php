<?php

namespace App\Jobs;

use App\Events\UserOnlineStatusChanged;
use App\Models\AiMessageTurn;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SetAiOffline implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $aiUserId) {}

    public function handle(): void
    {
        $aiUser = User::query()
            ->where('hesap_tipi', 'ai')
            ->find($this->aiUserId);

        if (!$aiUser) {
            return;
        }

        $aiUser->forceFill([
            'cevrim_ici_mi' => false,
            'son_gorulme_tarihi' => now(),
        ])->save();

        $nextOnlineAt = now()->addMinutes(5);
        AiMessageTurn::query()
            ->where('ai_user_id', $aiUser->id)
            ->whereIn('status', [AiMessageTurn::STATUS_PENDING, AiMessageTurn::STATUS_PROCESSING])
            ->update([
                'status' => AiMessageTurn::STATUS_DEFERRED,
                'planned_at' => $nextOnlineAt,
            ]);

        UserOnlineStatusChanged::dispatch($aiUser->id, false);
    }
}
