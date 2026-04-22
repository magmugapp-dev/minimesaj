<?php

namespace App\Policies;

use App\Models\InstagramHesap;
use App\Models\User;

class InstagramHesapPolicy
{
    public function yonet(User $user, InstagramHesap $hesap): bool
    {
        return $hesap->user_id === $user->id;
    }
}
