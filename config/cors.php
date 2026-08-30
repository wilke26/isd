<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Explicit comma-separated origins keep production deploys configurable
    // without falling back to a wildcard. Values must be full browser origins
    // (scheme + host + optional port), without paths or trailing slashes.
    'allowed_origins' => array_values(array_filter(
        array_map(
            static fn (string $origin): string => trim($origin),
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')),
        ),
        static fn (string $origin): bool => $origin !== '',
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
