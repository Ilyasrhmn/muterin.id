<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            $view->with('navbarNotifications', auth()->check()
                ? app(\App\Services\NotificationCenterService::class)->forUser(auth()->user())
                : collect());
        });
    }
}
