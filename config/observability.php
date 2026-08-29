<?php

declare(strict_types=1);

return [
    // Keep the Prometheus endpoint closed unless an explicit secret is set.
    // Supply the same value as a Bearer token in the Prometheus scrape config.
    'metrics_token' => env('METRICS_TOKEN'),
];
