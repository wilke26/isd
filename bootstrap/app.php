<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\RecordRequestMetrics;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Auf jeden Request angewendet, inkl. /up und /metrics — Request-ID
        // zuerst, damit sie in allen nachfolgenden Log-Zeilen verfügbar ist.
        $middleware->append([
            AssignRequestId::class,
            RecordRequestMetrics::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
