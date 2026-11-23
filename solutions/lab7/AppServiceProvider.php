<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Policies in app/Policies are auto-discovered by Laravel (Project -> ProjectPolicy, Order -> OrderPolicy).
     * Note: a Policy only protects a resource where it is actually *called*.
     */
    public function boot(): void
    {
        // Lab 7 Part E: per-user limits for the order API (falls back to the IP if no user is resolved).
        RateLimiter::for('orders-read', function (Request $request) {
            return Limit::perMinute(60)->by($this->limiterKey($request));
        });

        RateLimiter::for('orders-update', function (Request $request) {
            return Limit::perMinute(20)->by($this->limiterKey($request));
        });

        RateLimiter::for('orders-process', function (Request $request) {
            return Limit::perMinute(5)->by($this->limiterKey($request));
        });
    }

    private function limiterKey(Request $request): string
    {
        return $request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
    }
}
