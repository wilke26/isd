<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\License;
use App\Models\LicenseAssignment;
use App\Models\Role;
use App\Models\User;
use App\Policies\LicensePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicensePolicyTest extends TestCase
{
    use RefreshDatabase;

    private LicensePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new LicensePolicy;
    }

    public function test_admin_and_agent_can_manage_licenses(): void
    {
        $license = License::factory()->create();
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');

        foreach ([$admin, $agent] as $staffUser) {
            $this->assertTrue($this->policy->viewAny($staffUser));
            $this->assertTrue($this->policy->create($staffUser));
            $this->assertTrue($this->policy->update($staffUser, $license));
            $this->assertTrue($this->policy->assign($staffUser, $license));
        }
    }

    public function test_requester_cannot_access_license_management(): void
    {
        $license = License::factory()->create();
        $requester = $this->userWithRole('user');

        $this->assertFalse($this->policy->viewAny($requester));
        $this->assertFalse($this->policy->view($requester, $license));
        $this->assertFalse($this->policy->create($requester));
        $this->assertFalse($this->policy->update($requester, $license));
        $this->assertFalse($this->policy->assign($requester, $license));
    }

    public function test_only_admin_can_delete_an_unassigned_license(): void
    {
        $license = License::factory()->create();

        $this->assertTrue($this->policy->delete($this->userWithRole('admin'), $license));
        $this->assertTrue($this->policy->restore($this->userWithRole('admin'), $license));
        $this->assertTrue($this->policy->forceDelete($this->userWithRole('admin'), $license));
        $this->assertFalse($this->policy->delete($this->userWithRole('agent'), $license));
    }

    public function test_license_with_assignments_cannot_be_deleted(): void
    {
        $license = License::factory()->create();
        LicenseAssignment::factory()->create(['license_id' => $license->id]);
        $admin = $this->userWithRole('admin');

        $this->assertFalse($this->policy->delete($admin, $license));
        $this->assertFalse($this->policy->forceDelete($admin, $license));
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
