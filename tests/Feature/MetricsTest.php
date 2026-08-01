<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_is_reachable_without_authentication(): void
    {
        $this->get('/metrics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    public function test_metrics_endpoint_reports_ticket_counts_by_status(): void
    {
        Ticket::factory()->open()->count(2)->create();
        Ticket::factory()->resolved()->create();

        $response = $this->get('/metrics');

        $response->assertOk();
        $response->assertSee('isd_tickets_by_status{status="open"} 2', false);
        $response->assertSee('isd_tickets_by_status{status="resolved"} 1', false);
        $response->assertSee('isd_tickets_by_status{status="closed"} 0', false);
    }

    public function test_metrics_endpoint_declares_all_expected_metric_types(): void
    {
        $response = $this->get('/metrics');

        $response->assertSee('# TYPE isd_tickets_by_status gauge', false);
        $response->assertSee('# TYPE isd_http_requests_total counter', false);
        $response->assertSee('# TYPE isd_http_request_duration_seconds_sum counter', false);
        $response->assertSee('# TYPE isd_http_request_duration_seconds_count counter', false);
    }

    public function test_response_includes_request_id_header(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('X-Request-Id');
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_incoming_request_id_is_preserved(): void
    {
        $response = $this->withHeader('X-Request-Id', 'test-fixed-id-123')
            ->get('/up');

        $response->assertHeader('X-Request-Id', 'test-fixed-id-123');
    }
}
