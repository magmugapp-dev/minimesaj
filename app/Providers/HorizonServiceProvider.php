<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Horizon dark mode
        // Horizon::night();
    }

    /**
     * Horizon dashboard'a erişim kontrolü.
     * Production'da sadece admin kullanıcılar görebilir.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return $user->hesap_tipi === 'admin';
        });
    }
}
