<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class RecordRequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('metrics_start', microtime(true));

        return $next($request);
    }

    /**
     * Läuft nach dem Versenden der Response an den Client (terminable
     * middleware) — Metrik- und Log-Versand verzögern damit nicht die
     * wahrgenommene Antwortzeit des Requests.
     */
    public function terminate(Request $request, Response $response): void
    {
        $start = $request->attributes->get('metrics_start');
        if ($start === null) {
            return;
        }

        $duration = microtime(true) - $start;
        $method = $request->method();
        // Die Routen-URI (z.B. "api/v1/tickets/{id}") statt der konkreten URL
        // verwenden, sonst würde jede Ticket-ID eine eigene Zeitreihe erzeugen.
        $route = $request->route()?->uri() ?? 'unmatched';
        $status = $response->getStatusCode();
        $key = "{$method}|{$route}";

        // Metriken sind ein Nice-to-have — ein nicht erreichbares Redis darf
        // niemals den eigentlichen Request zum Scheitern bringen.
        try {
            Redis::pipeline(function ($pipe) use ($key, $status, $duration) {
                $pipe->hincrby('metrics:http_requests_total', "{$key}|{$status}", 1);
                $pipe->hincrbyfloat('metrics:http_request_duration_seconds_sum', $key, $duration);
                $pipe->hincrby('metrics:http_request_duration_seconds_count', $key, 1);
            });
        } catch (\Throwable) {
            // bewusst verschluckt
        }

        Log::channel('loki')->info('http_request_completed', [
            'request_id' => $request->attributes->get('request_id'),
            'method' => $method,
            'route' => $route,
            'status' => $status,
            'duration_ms' => round($duration * 1000, 2),
        ]);
    }
}
