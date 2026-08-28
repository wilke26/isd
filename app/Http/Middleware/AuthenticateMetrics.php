<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('observability.metrics_token');

        if (! is_string($expectedToken) || $expectedToken === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Metrics endpoint is not configured.');
        }

        $providedToken = $request->bearerToken();

        if (! is_string($providedToken) || ! hash_equals($expectedToken, $providedToken)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid metrics token.');
        }

        return $next($request);
    }
}
