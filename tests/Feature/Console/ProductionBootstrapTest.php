<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;
use LogicException;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProductionBootstrapTest extends TestCase
{
    public function test_default_seeder_omits_development_fixtures_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('isd.demo_seeding_enabled', true);

        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('asset_categories', 7);
        $this->assertDatabaseCount('asset_statuses', 5);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_production_reference_data_seeding_is_idempotent(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('isd.demo_seeding_enabled', false);

        $this->app->make(DatabaseSeeder::class)->run();
        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('asset_categories', 7);
        $this->assertDatabaseCount('asset_statuses', 5);
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_user_seeder_rejects_direct_execution_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Development users require local or testing environment');

        $this->app->make(UserSeeder::class)->run();
    }

    public function test_default_seeder_preserves_development_fixtures_in_testing(): void
    {
        config()->set('isd.demo_seeding_enabled', true);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@isd.local']);
        $this->assertTrue(
            User::where('email', 'admin@isd.local')->firstOrFail()->hasRole('admin'),
        );
    }

    public function test_local_environment_alone_does_not_enable_development_fixtures(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('isd.demo_seeding_enabled', false);

        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_direct_user_seeder_requires_explicit_opt_in(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('isd.demo_seeding_enabled', false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ISD_DEMO_SEEDING_ENABLED=true');

        $this->app->make(UserSeeder::class)->run();
    }

    public function test_artisan_environment_override_cannot_enable_production_demo_fixtures(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'isd-production-seeding-');

        if ($database === false) {
            self::fail('Unable to create the temporary SQLite database.');
        }

        $environment = [
            'APP_CONFIG_CACHE' => dirname($database) . '/missing-config.php',
            'APP_ENV' => 'production',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'DB_URL' => '',
            'ISD_DEMO_SEEDING_ENABLED' => 'true',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];

        try {
            (new Process(
                [PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'],
                base_path(),
                $environment,
            ))->mustRun();

            foreach ([['--env=local'], ['--env', 'local']] as $environmentOverride) {
                (new Process(
                    [
                        PHP_BINARY,
                        'artisan',
                        'db:seed',
                        '--force',
                        '--no-interaction',
                        ...$environmentOverride,
                    ],
                    base_path(),
                    $environment,
                ))->mustRun();
            }

            $connection = new PDO('sqlite:' . $database);

            self::assertSame(
                0,
                (int) $connection->query("SELECT COUNT(*) FROM users WHERE email = 'admin@isd.local'")->fetchColumn(),
            );
            self::assertSame(
                3,
                (int) $connection->query('SELECT COUNT(*) FROM roles')->fetchColumn(),
            );
            self::assertSame(
                7,
                (int) $connection->query('SELECT COUNT(*) FROM asset_categories')->fetchColumn(),
            );
            self::assertSame(
                5,
                (int) $connection->query('SELECT COUNT(*) FROM asset_statuses')->fetchColumn(),
            );
        } finally {
            if (is_file($database)) {
                unlink($database);
            }
        }
    }

    public function test_admin_bootstrap_creates_a_unique_privileged_user(): void
    {
        $password = 'Correct-Horse-42!';

        $this->artisan('isd:create-admin', [
            '--email' => 'owner@example.com',
            '--name' => 'Repository Owner',
        ])
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutputToContain('Administrator created successfully.')
            ->assertSuccessful();

        $admin = User::where('email', 'owner@example.com')->firstOrFail();

        $this->assertSame('Repository Owner', $admin->name);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_admin_bootstrap_rejects_weak_passwords(): void
    {
        $this->artisan('isd:create-admin', ['--email' => 'owner@example.com'])
            ->expectsQuestion('Password', 'password')
            ->expectsQuestion('Confirm password', 'password')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }

    public function test_admin_bootstrap_does_not_escalate_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('isd:create-admin', ['--email' => $user->email])
            ->expectsOutputToContain('A user with this email address already exists.')
            ->assertFailed();

        $this->assertFalse($user->fresh()->hasRole('admin'));
    }
}
