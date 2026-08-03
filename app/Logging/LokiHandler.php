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
     * request_id bewusst NICHT als Loki-Label, sondern als Feld im JSON-Body —
     * Labels erzeugen bei Loki pro eindeutigem Wert eine eigene Zeitreihe,
     * bei einer pro-Request eindeutigen ID würde das die Kardinalität
     * unbegrenzt wachsen lassen. Als JSON-Feld ist die ID trotzdem per
     * LogQL-`| json`-Pipeline-Stage durchsuchbar.
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
            // Log-Versand darf den eigentlichen Request niemals zum Scheitern
            // bringen — Fehler hier werden bewusst verschluckt.
        }
    }
}
