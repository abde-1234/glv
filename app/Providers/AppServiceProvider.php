<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('password-reset-requests', function (Request $request): array {
            $identity = Str::lower($request->string('email')->toString());

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(3)->by($identity.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
    }
}
