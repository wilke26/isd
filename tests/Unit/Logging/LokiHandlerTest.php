<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LokiHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_loki_channel_pushes_structured_log_via_http(): void
    {
        Http::fake();

        Log::channel('loki')->info('http_request_completed', [
            'request_id' => 'abc-123-fixed',
            'method'     => 'GET',
            'status'     => 200,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/loki/api/v1/push')
                && str_contains($request->body(), 'abc-123-fixed')
                && str_contains($request->body(), 'http_request_completed');
        });
    }

    public function test_loki_push_failure_does_not_throw(): void
    {
        // Simuliert ein nicht erreichbares Loki — der Log-Aufruf selbst darf
        // dabei niemals eine Exception werfen.
        Http::fake(fn () => throw new \RuntimeException('Connection refused'));

        Log::channel('loki')->info('test_event');

        $this->assertTrue(true); // Kein Exception-Abbruch bis hierhin = bestanden
    }
}
