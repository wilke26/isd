<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\License;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_open_license_management(): void
    {
        $agent = $this->userWithRole('agent');
        $license = License::factory()->create();

        $this->actingAs($agent)
            ->get('/app/licenses')
            ->assertOk()
            ->assertSee('Lizenzen');

        $this->get('/app/licenses/create')->assertOk();
        $this->get("/app/licenses/{$license->id}/edit")->assertOk();
    }

    public function test_requester_cannot_open_license_management(): void
    {
        $this->actingAs($this->userWithRole('user'))
            ->get('/app/licenses')
            ->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug)],
        );
        $user->roles()->attach($role);

        return $user;
    }
}
