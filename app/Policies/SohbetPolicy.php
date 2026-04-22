<?php

namespace App\Policies;

use App\Models\Sohbet;
use App\Models\User;

class SohbetPolicy
{
    public function erisebilir(User $user, Sohbet $sohbet): bool
    {
        $eslesme = $sohbet->eslesme;

        return $eslesme->user_id === $user->id || $eslesme->eslesen_user_id === $user->id;
    }
}
