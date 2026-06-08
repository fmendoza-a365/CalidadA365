<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        RateLimiter::for('ai-provider', function () {
            $perMinute = max(1, (int) config('queue.ai.provider_per_minute', 2));

            return Limit::perMinute($perMinute)->by('global');
        });

        // Force HTTPS URLs in production behind Nginx or a load balancer.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Eager-load roles and permissions once per request to avoid N+1
        // queries in Blade templates (sidebar checks hasRole/can 13+ times).
        Auth::user()?->loadMissing('roles', 'permissions');
    }
}
