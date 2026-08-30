<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ArticleStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\KbArticle;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_resources_require_authentication(): void
    {
        $ticket = Ticket::factory()->create();
        $asset = Asset::factory()->create();
        $article = KbArticle::factory()->published()->create();

        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertUnauthorized();
        $this->getJson("/api/v1/assets/{$asset->id}")->assertUnauthorized();
        $this->getJson("/api/v1/kb/articles/{$article->id}")->assertUnauthorized();
    }

    public function test_foreign_ticket_is_indistinguishable_from_a_missing_ticket(): void
    {
        $owner = User::factory()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $owner->id]);

        $this->actingAsUser();

        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertNotFound();
        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['priority' => 'high'])->assertNotFound();
        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'body' => 'Probe',
            'is_internal' => false,
        ])->assertNotFound();

        $this->assertDatabaseMissing('ticket_comments', [
            'ticket_id' => $ticket->id,
            'body' => 'Probe',
        ]);
    }

    public function test_visible_ticket_still_returns_forbidden_for_staff_only_actions(): void
    {
        $requester = $this->actingAsUser();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", ['priority' => 'high'])
            ->assertForbidden();

        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'body' => 'Interne Notiz',
            'is_internal' => true,
        ])->assertForbidden();
    }

    public function test_internal_ticket_comments_are_not_exposed_to_requester(): void
    {
        $requester = $this->actingAsUser();
        $agent = $this->createAgent();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'Nur für Support sichtbar',
            'is_internal' => true,
        ]);
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'Öffentliche Rückmeldung',
            'is_internal' => false,
        ]);

        $this->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('data.comments.0.body', 'Öffentliche Rückmeldung')
            ->assertJsonMissing(['body' => 'Nur für Support sichtbar']);
    }

    public function test_foreign_asset_is_indistinguishable_from_a_missing_asset(): void
    {
        $owner = User::factory()->create();
        $asset = Asset::factory()->create();
        AssetAssignment::create([
            'asset_id' => $asset->id,
            'user_id' => $owner->id,
            'assigned_at' => now(),
        ]);

        $this->actingAsUser();

        $this->getJson("/api/v1/assets/{$asset->id}")->assertNotFound();
        $this->getJson("/api/v1/assets/{$asset->id}/history")->assertNotFound();
    }

    public function test_visible_asset_history_remains_staff_only(): void
    {
        $requester = $this->actingAsUser();
        $asset = Asset::factory()->create();
        AssetAssignment::create([
            'asset_id' => $asset->id,
            'user_id' => $requester->id,
            'assigned_at' => now(),
        ]);

        $this->getJson("/api/v1/assets/{$asset->id}")->assertOk();
        $this->getJson("/api/v1/assets/{$asset->id}/history")->assertForbidden();
    }

    public function test_foreign_private_article_is_indistinguishable_from_a_missing_article(): void
    {
        $author = User::factory()->create();
        $article = KbArticle::factory()->draft()->create(['author_id' => $author->id]);

        $this->actingAsUser();

        $this->getJson("/api/v1/kb/articles/{$article->id}")->assertNotFound();
        $this->postJson("/api/v1/kb/articles/{$article->id}/submit")->assertNotFound();
    }

    public function test_visible_published_article_still_enforces_mutation_policy(): void
    {
        $article = KbArticle::factory()->create(['status' => ArticleStatus::Published]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/kb/articles/{$article->id}")->assertOk();
        $this->postJson("/api/v1/kb/articles/{$article->id}/addendum", [
            'text' => 'Unzulässiger Nachtrag',
        ])->assertForbidden();
    }
}
