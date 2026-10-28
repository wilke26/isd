<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Development fixtures
    |--------------------------------------------------------------------------
    |
    | Demo users have public credentials. Creating them requires a configured
    | local or testing environment, no Artisan --env override, and this explicit
    | opt-in. The default remains disabled as an additional safeguard.
    |
    */
    'demo_seeding_enabled' => (bool) env('ISD_DEMO_SEEDING_ENABLED', false),
];
