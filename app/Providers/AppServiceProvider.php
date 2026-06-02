<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

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
    }
}
