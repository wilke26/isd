<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $agentRole = Role::where('slug', 'agent')->first();
        $userRole = Role::where('slug', 'user')->first();

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@isd.local'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
            ],
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        // Agents
        $agents = [
            ['name' => 'Anna Müller',   'email' => 'a.mueller@isd.local'],
            ['name' => 'Ben Schmidt',   'email' => 'b.schmidt@isd.local'],
        ];

        foreach ($agents as $agentData) {
            $agent = User::firstOrCreate(
                ['email' => $agentData['email']],
                ['name' => $agentData['name'], 'password' => Hash::make('password')],
            );
            $agent->roles()->syncWithoutDetaching([$agentRole->id]);
        }

        // Normale Benutzer
        $users = [
            ['name' => 'Clara Weber',   'email' => 'c.weber@isd.local'],
            ['name' => 'David Bauer',   'email' => 'd.bauer@isd.local'],
            ['name' => 'Eva Fischer',   'email' => 'e.fischer@isd.local'],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => Hash::make('password')],
            );
            $user->roles()->syncWithoutDetaching([$userRole->id]);
        }
    }
}
