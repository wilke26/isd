<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the duration and outcome of every request for /metrics (Redis)
 * and optionally for Loki — as terminable middleware, see terminate().
 */
class RecordRequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('metrics_start', microtime(true));

        return $next($request);
    }

    /**
     * Runs after the response has been sent to the client (terminable
     * middleware) — sending metrics and logs therefore doesn't delay the
     * perceived response time of the request.
     */
    public function terminate(Request $request, Response $response): void
    {
        $start = $request->attributes->get('metrics_start');
        if ($start === null) {
            return;
        }

        $duration = microtime(true) - $start;
        $method = $request->method();
        // Use the route URI (e.g. "api/v1/tickets/{id}") instead of the
        // concrete URL, otherwise every ticket ID would create its own time series.
        $route = $request->route()?->uri() ?? 'unmatched';
        $status = $response->getStatusCode();
        $key = "{$method}|{$route}";

        // Metrics are a nice-to-have — an unreachable Redis must never
        // cause the actual request to fail.
        try {
            Redis::pipeline(function ($pipe) use ($key, $status, $duration) {
                $pipe->hincrby('metrics:http_requests_total', "{$key}|{$status}", 1);
                $pipe->hincrbyfloat('metrics:http_request_duration_seconds_sum', $key, $duration);
                $pipe->hincrby('metrics:http_request_duration_seconds_count', $key, 1);
            });
        } catch (\Throwable) {
            // deliberately swallowed
        }

        // The Loki push is optional and disabled by default (see
        // LOKI_ENABLED in .env) — without a running observability stack,
        // every request would otherwise trigger an up-to-two-second HTTP
        // timeout attempt in the background (LokiHandler does catch the
        // error, but the resource usage would still be unnecessary).
        if (config('logging.loki_enabled')) {
            Log::channel('loki')->info('http_request_completed', [
                'request_id' => $request->attributes->get('request_id'),
                'method' => $method,
                'route' => $route,
                'status' => $status,
                'duration_ms' => round($duration * 1000, 2),
            ]);
        }
    }
}
