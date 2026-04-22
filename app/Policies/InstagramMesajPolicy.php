<?php

namespace App\Policies;

use App\Models\InstagramMesaj;
use App\Models\User;

class InstagramMesajPolicy
{
    public function isaretle(User $user, InstagramMesaj $mesaj): bool
    {
        return $mesaj->hesap->user_id === $user->id;
    }
}
