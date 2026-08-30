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
     * Register application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        // Combines email and IP: prevents both many attempts from a single
        // IP with varying email addresses and credential stuffing of the
        // same email address across many different IPs.
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('email')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'Zu viele Login-Versuche. Bitte versuche es in Kürze erneut.',
                ], 429, $headers);
            });
        });

        // Protect every authenticated API consumer independently. A user ID
        // is stable across changing client IPs and prevents one noisy portal
        // session from consuming the allowance of unrelated users behind the
        // same corporate proxy.
        RateLimiter::for('api', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) config('api.rate_limit_per_minute'))
                ->by($userId !== null ? "user:{$userId}" : "ip:{$request->ip()}")
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Zu viele API-Anfragen. Bitte versuche es in Kürze erneut.',
                    ], 429, $headers);
                });
        });
    }
}
