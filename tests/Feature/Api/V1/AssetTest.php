<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    // ─── Index ────────────────────────────────────────────────────

    public function test_admin_can_list_all_assets(): void
    {
        $this->actingAsAdmin();
        Asset::factory()->count(3)->create();

        $this->getJson('/api/v1/assets')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'asset_tag', 'name', 'category', 'status']],
            ]);
    }

    public function test_assets_can_be_filtered_by_category(): void
    {
        $this->actingAsAdmin();

        $laptops = AssetCategory::factory()->create(['name' => 'Laptops']);
        $servers = AssetCategory::factory()->create(['name' => 'Server']);

        Asset::factory()->create(['asset_category_id' => $laptops->id]);
        Asset::factory()->create(['asset_category_id' => $servers->id]);

        $this->getJson("/api/v1/assets?category_id={$laptops->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_assets_can_be_searched(): void
    {
        $this->actingAsAdmin();

        Asset::factory()->create(['name' => 'MacBook Pro', 'asset_tag' => 'NB-001']);
        Asset::factory()->create(['name' => 'ThinkPad X1', 'asset_tag' => 'NB-002']);

        $this->getJson('/api/v1/assets?search=MacBook')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'MacBook Pro');
    }

    // ─── Store ────────────────────────────────────────────────────

    public function test_admin_can_create_asset(): void
    {
        $this->actingAsAdmin();
        $category = AssetCategory::factory()->create();
        $status   = AssetStatus::factory()->available()->create();

        $response = $this->postJson('/api/v1/assets', [
            'asset_tag'         => 'NB-999',
            'name'              => 'Test Laptop',
            'asset_category_id' => $category->id,
            'asset_status_id'   => $status->id,
            'manufacturer'      => 'Dell',
            'model'             => 'XPS 15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('asset_tag', 'NB-999')
            ->assertJsonPath('name', 'Test Laptop');

        $this->assertDatabaseHas('assets', ['asset_tag' => 'NB-999']);
    }

    public function test_asset_tag_must_be_unique(): void
    {
        $this->actingAsAdmin();
        $asset    = Asset::factory()->create(['asset_tag' => 'NB-001']);
        $category = AssetCategory::factory()->create();
        $status   = AssetStatus::factory()->create();

        $this->postJson('/api/v1/assets', [
            'asset_tag'         => 'NB-001',
            'name'              => 'Anderer Laptop',
            'asset_category_id' => $category->id,
            'asset_status_id'   => $status->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_tag']);
    }

    public function test_asset_creation_requires_mandatory_fields(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/assets', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_tag', 'name', 'asset_category_id', 'asset_status_id']);
    }

    // ─── Show ─────────────────────────────────────────────────────

    public function test_can_show_asset_with_relations(): void
    {
        $this->actingAsAdmin();
        $asset = Asset::factory()->create();

        $this->getJson("/api/v1/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'asset_tag', 'name', 'category', 'status', 'parent']]);
    }

    // ─── Zuweisung ────────────────────────────────────────────────

    public function test_admin_can_assign_asset_to_user(): void
    {
        $this->actingAsAdmin();
        $asset = Asset::factory()->create();
        $user  = User::factory()->create();

        $this->postJson("/api/v1/assets/{$asset->id}/assign", [
            'user_id' => $user->id,
        ])->assertOk();

        $this->assertDatabaseHas('asset_assignments', [
            'asset_id'    => $asset->id,
            'user_id'     => $user->id,
            'returned_at' => null,
        ]);
    }

    public function test_assigning_to_new_user_closes_previous_assignment(): void
    {
        $this->actingAsAdmin();
        $asset   = Asset::factory()->create();
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        // Erste Zuweisung
        $this->postJson("/api/v1/assets/{$asset->id}/assign", ['user_id' => $userOne->id]);

        // Zweite Zuweisung
        $this->postJson("/api/v1/assets/{$asset->id}/assign", ['user_id' => $userTwo->id]);

        // Alte Zuweisung muss returned_at haben
        $this->assertDatabaseMissing('asset_assignments', [
            'asset_id'    => $asset->id,
            'user_id'     => $userOne->id,
            'returned_at' => null,
        ]);

        // Neue Zuweisung ist aktiv
        $this->assertDatabaseHas('asset_assignments', [
            'asset_id'    => $asset->id,
            'user_id'     => $userTwo->id,
            'returned_at' => null,
        ]);
    }

    public function test_admin_can_unassign_asset(): void
    {
        $this->actingAsAdmin();
        $asset = Asset::factory()->create();
        $user  = User::factory()->create();

        AssetAssignment::create([
            'asset_id'    => $asset->id,
            'user_id'     => $user->id,
            'assigned_at' => now(),
        ]);

        $this->deleteJson("/api/v1/assets/{$asset->id}/assign")
            ->assertOk();

        $this->assertDatabaseMissing('asset_assignments', [
            'asset_id'    => $asset->id,
            'returned_at' => null,
        ]);
    }

    public function test_can_retrieve_asset_assignment_history(): void
    {
        $this->actingAsAdmin();
        $asset = Asset::factory()->create();
        $user  = User::factory()->create();

        AssetAssignment::create([
            'asset_id'    => $asset->id,
            'user_id'     => $user->id,
            'assigned_at' => now()->subMonth(),
            'returned_at' => now()->subWeek(),
        ]);

        $this->getJson("/api/v1/assets/{$asset->id}/history")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([['user', 'assigned_at', 'returned_at']]);
    }
}
