<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'isd:create-admin
        {--email= : Administrator email address}
        {--name=System Administrator : Administrator display name}';

    protected $description = 'Create the initial administrator without development fixtures';

    public function handle(): int
    {
        $emailOption = $this->option('email');
        $nameOption = $this->option('name');
        $email = is_string($emailOption) && $emailOption !== ''
            ? Str::lower(trim($emailOption))
            : Str::lower(trim((string) $this->ask('Email address')));
        $name = is_string($nameOption) ? $nameOption : '';

        $identityValidator = Validator::make(
            ['email' => $email, 'name' => $name],
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
            ],
        );

        if ($identityValidator->fails()) {
            $this->components->error($identityValidator->errors()->first());

            return self::FAILURE;
        }

        if (User::firstWhere('email', $email) !== null) {
            $this->components->error('A user with this email address already exists.');

            return self::FAILURE;
        }

        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');
        $passwordValidator = Validator::make(
            [
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'password' => [
                    'required',
                    'string',
                    'confirmed',
                    Password::min(12)->mixedCase()->numbers()->symbols(),
                ],
            ],
        );

        if ($passwordValidator->fails()) {
            $this->components->error($passwordValidator->errors()->first());

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($email, $name, $password): void {
                $adminRole = Role::firstOrCreate(
                    ['slug' => 'admin'],
                    ['name' => 'Administrator'],
                );
                $admin = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ]);

                $admin->roles()->attach($adminRole);
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! DB::table('users')->where('email', $email)->exists()) {
                throw $exception;
            }

            $this->components->error('A user with this email address already exists.');

            return self::FAILURE;
        }

        $this->components->info('Administrator created successfully.');

        return self::SUCCESS;
    }
}
