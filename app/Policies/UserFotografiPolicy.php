<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserFotografi;

class UserFotografiPolicy
{
    public function sil(User $user, UserFotografi $fotograf): bool
    {
        return $fotograf->user_id === $user->id;
    }
}
