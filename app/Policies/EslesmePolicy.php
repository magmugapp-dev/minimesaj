<?php

namespace App\Policies;

use App\Models\Eslesme;
use App\Models\User;

class EslesmePolicy
{
    public function bitir(User $user, Eslesme $eslesme): bool
    {
        return $eslesme->user_id === $user->id || $eslesme->eslesen_user_id === $user->id;
    }
}
