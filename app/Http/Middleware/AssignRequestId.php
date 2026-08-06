<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Versieht jeden Request mit einer eindeutigen ID (Request-Attribut,
 * Log-Kontext und Response-Header), damit sich sein Verlauf durchgängig
 * nachverfolgen lässt.
 */
class AssignRequestId
{
    private const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        // Falls ein vorgelagerter Proxy/Load-Balancer bereits eine Request-ID
        // mitschickt, diese übernehmen statt eine neue zu erzeugen — so bleibt
        // die Kette über mehrere Systeme hinweg nachvollziehbar.
        $requestId = $request->header(self::HEADER) ?: (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        // Wird jedem Log-Aufruf innerhalb dieses Requests automatisch beigefügt.
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
