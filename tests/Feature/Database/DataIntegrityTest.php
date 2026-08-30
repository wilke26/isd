<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_a_second_active_assignment_for_the_same_asset(): void
    {
        $asset = Asset::factory()->create();

        AssetAssignment::create([
            'asset_id' => $asset->id,
            'user_id' => User::factory()->create()->id,
            'assigned_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        AssetAssignment::create([
            'asset_id' => $asset->id,
            'user_id' => User::factory()->create()->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_database_preserves_multiple_historical_assignments(): void
    {
        $asset = Asset::factory()->create();

        foreach (range(1, 2) as $daysAgo) {
            AssetAssignment::create([
                'asset_id' => $asset->id,
                'user_id' => User::factory()->create()->id,
                'assigned_at' => now()->subDays($daysAgo + 1),
                'returned_at' => now()->subDays($daysAgo),
            ]);
        }

        $this->assertDatabaseCount('asset_assignments', 2);
    }

    public function test_query_supporting_indexes_are_present(): void
    {
        $indexNames = static fn (string $table): array => array_column(Schema::getIndexes($table), 'name');

        $this->assertContains('asset_assignments_one_active_unique', $indexNames('asset_assignments'));
        $this->assertContains('asset_assignments_user_current_index', $indexNames('asset_assignments'));
        $this->assertContains('tickets_requester_created_index', $indexNames('tickets'));
        $this->assertContains('kb_articles_status_published_index', $indexNames('kb_articles'));
        $this->assertNotContains('kb_articles_slug_index', $indexNames('kb_articles'));
    }
}
