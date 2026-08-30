<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authenticated API rate limit
    |--------------------------------------------------------------------------
    |
    | Applied per authenticated user to all /api/v1 routes except login,
    | which has its own stricter credential-focused limiter.
    |
    */
    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
];
