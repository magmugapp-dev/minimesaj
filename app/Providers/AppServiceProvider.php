<?php

namespace App\Providers;

use App\Models\Odeme;
use App\Services\EslesmeServisi;
use App\Services\Kimlik\Sosyal\AppleSosyalKimlikSaglayici;
use App\Services\Kimlik\Sosyal\GoogleSosyalKimlikSaglayici;
use App\Services\MesajServisi;
use App\Services\Notifications\FcmService;
use App\Services\PuanServisi;
use App\Services\YapayZeka\AiServisi;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiServisi::class);
        $this->app->singleton(EslesmeServisi::class);
        $this->app->singleton(MesajServisi::class);
        $this->app->singleton(FcmService::class);
        $this->app->singleton(PuanServisi::class);
        $this->app->singleton('social.provider.apple', AppleSosyalKimlikSaglayici::class);
        $this->app->singleton('social.provider.google', GoogleSosyalKimlikSaglayici::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->disableConsoleExecutionLimit();
        }

        View::composer('admin.layout.ana', function ($view) {
            $adminNavbarGunlukBakiye = Odeme::query()
                ->where('durum', 'basarili')
                ->whereDate('created_at', today())
                ->sum('tutar');

            $view->with('adminNavbarGunlukBakiye', $adminNavbarGunlukBakiye);
        });
    }

    private function disableConsoleExecutionLimit(): void
    {
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', '0');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }
}
