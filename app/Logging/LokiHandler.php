<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class LokiHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $job = 'isd',
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * request_id is deliberately NOT a Loki label but a field in the JSON
     * body — in Loki, labels create a separate time series per unique
     * value, and with an ID unique per request that would make cardinality
     * grow without bound. As a JSON field, the ID is still searchable via
     * the LogQL `| json` pipeline stage.
     */
    protected function write(LogRecord $record): void
    {
        $labels = [
            'job' => $this->job,
            'level' => strtolower($record->level->getName()),
        ];

        $line = json_encode([
            'message' => $record->message,
            'level' => $record->level->getName(),
            ...$record->context,
        ]);

        $seconds = $record->datetime->getTimestamp();
        $micros = (int) $record->datetime->format('u');
        $timestampNs = (string) (($seconds * 1_000_000_000) + ($micros * 1_000));

        try {
            Http::timeout(2)->post($this->endpoint, [
                'streams' => [
                    [
                        'stream' => $labels,
                        'values' => [[$timestampNs, $line]],
                    ],
                ],
            ]);
        } catch (\Throwable) {
            // Sending the log must never cause the actual request to fail —
            // errors here are deliberately swallowed.
        }
    }
}
