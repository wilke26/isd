<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RecordRequestMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RecordRequestMetricsTest extends TestCase
{
    public function test_loki_push_is_skipped_when_disabled(): void
    {
        config(['logging.loki_enabled' => false]);
        Http::fake();

        $middleware = new RecordRequestMetrics;
        $request = Request::create('/api/v1/tickets', 'GET');
        $request->attributes->set('metrics_start', microtime(true) - 0.1);

        $middleware->terminate($request, new Response('', 200));

        Http::assertNothingSent();
    }

    public function test_loki_push_happens_when_enabled(): void
    {
        config(['logging.loki_enabled' => true]);
        Http::fake();

        $middleware = new RecordRequestMetrics;
        $request = Request::create('/api/v1/tickets', 'GET');
        $request->attributes->set('metrics_start', microtime(true) - 0.1);
        $request->attributes->set('request_id', 'test-fixed-id');

        $middleware->terminate($request, new Response('', 200));

        Http::assertSent(fn ($req) => str_contains($req->url(), '/loki/api/v1/push'));
    }
}
