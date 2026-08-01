<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [];

        $lines = [...$lines, ...$this->ticketStatusGauge()];
        $lines = [...$lines, ...$this->httpRequestCounters()];
        $lines = [...$lines, ...$this->httpDurationCounters()];

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    /** @return list<string> */
    private function ticketStatusGauge(): array
    {
        $lines = [
            '# HELP isd_tickets_by_status Anzahl Tickets je Status',
            '# TYPE isd_tickets_by_status gauge',
        ];

        $counts = Ticket::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        foreach (TicketStatus::cases() as $status) {
            $lines[] = sprintf(
                'isd_tickets_by_status{status="%s"} %d',
                $status->value,
                $counts[$status->value] ?? 0,
            );
        }

        return $lines;
    }

    /** @return list<string> */
    private function httpRequestCounters(): array
    {
        $lines = [
            '# HELP isd_http_requests_total Anzahl HTTP-Requests',
            '# TYPE isd_http_requests_total counter',
        ];

        try {
            foreach (Redis::hgetall('metrics:http_requests_total') as $key => $value) {
                [$method, $route, $status] = array_pad(explode('|', $key, 3), 3, 'unknown');
                $lines[] = sprintf(
                    'isd_http_requests_total{method="%s",route="%s",status="%s"} %d',
                    $method,
                    $route,
                    $status,
                    (int) $value,
                );
            }
        } catch (\Throwable) {
            // Redis nicht erreichbar — Zähler auslassen statt /metrics
            // komplett scheitern zu lassen.
        }

        return $lines;
    }

    /** @return list<string> */
    private function httpDurationCounters(): array
    {
        $lines = [
            '# HELP isd_http_request_duration_seconds_sum Summe der Antwortzeiten in Sekunden',
            '# TYPE isd_http_request_duration_seconds_sum counter',
        ];

        try {
            foreach (Redis::hgetall('metrics:http_request_duration_seconds_sum') as $key => $value) {
                [$method, $route] = array_pad(explode('|', $key, 2), 2, 'unknown');
                $lines[] = sprintf(
                    'isd_http_request_duration_seconds_sum{method="%s",route="%s"} %s',
                    $method,
                    $route,
                    $value,
                );
            }
        } catch (\Throwable) {
        }

        $lines[] = '# HELP isd_http_request_duration_seconds_count Anzahl gemessener Requests';
        $lines[] = '# TYPE isd_http_request_duration_seconds_count counter';

        try {
            foreach (Redis::hgetall('metrics:http_request_duration_seconds_count') as $key => $value) {
                [$method, $route] = array_pad(explode('|', $key, 2), 2, 'unknown');
                $lines[] = sprintf(
                    'isd_http_request_duration_seconds_count{method="%s",route="%s"} %d',
                    $method,
                    $route,
                    (int) $value,
                );
            }
        } catch (\Throwable) {
        }

        return $lines;
    }
}
