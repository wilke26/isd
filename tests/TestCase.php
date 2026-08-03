<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // ─── Hilfsmethoden für Benutzer mit Rollen ────────────────────

    protected function createAdmin(): User
    {
        return $this->createUserWithRole('admin');
    }

    protected function createAgent(): User
    {
        return $this->createUserWithRole('agent');
    }

    protected function createUser(): User
    {
        return $this->createUserWithRole('user');
    }

    protected function createUserWithRole(string $roleSlug): User
    {
        $role = Role::factory()->create(['slug' => $roleSlug]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    protected function actingAsAdmin(): User
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function actingAsAgent(): User
    {
        $agent = $this->createAgent();
        Sanctum::actingAs($agent);

        return $agent;
    }

    protected function actingAsUser(): User
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        return $user;
    }
}
