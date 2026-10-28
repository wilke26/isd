<?php

declare(strict_types=1);

namespace Database\Seeders;

use Symfony\Component\Console\Input\ArgvInput;

final class DemoSeedingGuard
{
    public static function allowsFixtures(): bool
    {
        if (self::hasCommandLineEnvironmentOverride()) {
            return false;
        }

        $configuredEnvironment = config('app.env');

        return is_string($configuredEnvironment)
            && in_array($configuredEnvironment, ['local', 'testing'], true)
            && app()->environment($configuredEnvironment)
            && config('isd.demo_seeding_enabled') === true;
    }

    private static function hasCommandLineEnvironmentOverride(): bool
    {
        return app()->runningInConsole()
            && (new ArgvInput)->hasParameterOption('--env');
    }
}
