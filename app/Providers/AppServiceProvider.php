<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Kombiniert E-Mail und IP: verhindert sowohl viele Versuche von
        // einer IP mit wechselnden E-Mail-Adressen als auch Credential-
        // Stuffing derselben E-Mail-Adresse über viele verschiedene IPs.
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('email')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($key)->response(function () {
                return response()->json([
                    'message' => 'Zu viele Login-Versuche. Bitte versuche es in Kürze erneut.',
                ], 429);
            });
        });
    }
}
