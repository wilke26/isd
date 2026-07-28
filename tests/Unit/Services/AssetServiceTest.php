<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AssetService();
    }

    public function test_assign_creates_active_assignment(): void
    {
        $asset = Asset::factory()->create();
        $user  = User::factory()->create();

        $assignment = $this->service->assign($asset, $user);

        $this->assertEquals($asset->id, $assignment->asset_id);
        $this->assertEquals($user->id, $assignment->user_id);
        $this->assertNull($assignment->returned_at);
    }

    public function test_assign_closes_previous_assignment(): void
    {
        $asset   = Asset::factory()->create();
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $this->service->assign($asset, $userOne);
        $this->service->assign($asset, $userTwo);

        $previousAssignment = AssetAssignment::where('asset_id', $asset->id)
            ->where('user_id', $userOne->id)
            ->first();

        $this->assertNotNull($previousAssignment->returned_at);
    }

    public function test_only_one_active_assignment_exists_at_a_time(): void
    {
        $asset   = Asset::factory()->create();
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $this->service->assign($asset, $userOne);
        $this->service->assign($asset, $userTwo);

        $activeAssignments = AssetAssignment::where('asset_id', $asset->id)
            ->whereNull('returned_at')
            ->count();

        $this->assertEquals(1, $activeAssignments);
    }

    public function test_unassign_sets_returned_at_on_active_assignment(): void
    {
        $asset = Asset::factory()->create();
        $user  = User::factory()->create();

        $this->service->assign($asset, $user);
        $this->service->unassign($asset);

        $this->assertDatabaseMissing('asset_assignments', [
            'asset_id'    => $asset->id,
            'returned_at' => null,
        ]);
    }

    public function test_unassign_has_no_effect_when_no_active_assignment(): void
    {
        $asset = Asset::factory()->create();

        // Kein Fehler, auch wenn kein aktives Assignment existiert
        $this->service->unassign($asset);

        $this->assertDatabaseCount('asset_assignments', 0);
    }

    public function test_assignment_history_returns_all_assignments(): void
    {
        $asset   = Asset::factory()->create();
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        AssetAssignment::create(['asset_id' => $asset->id, 'user_id' => $userOne->id,
            'assigned_at' => now()->subMonths(2), 'returned_at' => now()->subMonth()]);
        AssetAssignment::create(['asset_id' => $asset->id, 'user_id' => $userTwo->id,
            'assigned_at' => now()->subMonth()]);

        $history = $this->service->assignmentHistory($asset);

        $this->assertCount(2, $history);
    }
}
