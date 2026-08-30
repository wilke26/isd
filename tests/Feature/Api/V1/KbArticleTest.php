<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ArticleStatus;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KbArticleTest extends TestCase
{
    use RefreshDatabase;

    // ─── Index ────────────────────────────────────────────────────

    public function test_published_articles_are_visible_to_all_users(): void
    {
        $this->actingAsUser();

        KbArticle::factory()->published()->count(2)->create();
        KbArticle::factory()->draft()->create();

        $this->getJson('/api/v1/kb/articles')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_agents_can_see_draft_articles(): void
    {
        $this->actingAsAgent();

        KbArticle::factory()->published()->create();
        KbArticle::factory()->draft()->create();

        $this->getJson('/api/v1/kb/articles')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_articles_can_be_filtered_by_category(): void
    {
        $this->actingAsAdmin();

        $hardware = KbCategory::factory()->create(['name' => 'Hardware', 'slug' => 'hardware']);
        $software = KbCategory::factory()->create(['name' => 'Software', 'slug' => 'software']);

        KbArticle::factory()->published()->create(['category_id' => $hardware->id]);
        KbArticle::factory()->published()->create(['category_id' => $software->id]);

        $this->getJson("/api/v1/kb/articles?category_id={$hardware->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_articles_can_be_searched(): void
    {
        $this->actingAsAdmin();

        KbArticle::factory()->published()->create(['title' => 'VPN einrichten Windows']);
        KbArticle::factory()->published()->create(['title' => 'Passwort zurücksetzen']);

        $this->getJson('/api/v1/kb/articles?search=VPN')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'VPN einrichten Windows');
    }

    public function test_article_list_rejects_unbounded_pagination(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/kb/articles?per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    // ─── Store ────────────────────────────────────────────────────

    public function test_agent_can_create_draft_article(): void
    {
        $agent = $this->actingAsAgent();
        $category = KbCategory::factory()->create();

        $response = $this->postJson('/api/v1/kb/articles', [
            'title' => 'Neuer Artikel',
            'body' => 'Inhalt des Artikels...',
            'status' => 'draft',
            'category_id' => $category->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Neuer Artikel')
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.author.id', $agent->id);
    }

    public function test_publishing_article_sets_published_at(): void
    {
        $this->actingAsAgent();

        $response = $this->postJson('/api/v1/kb/articles', [
            'title' => 'Veröffentlichter Artikel',
            'body' => 'Inhalt...',
            'status' => 'published',
        ]);

        $response->assertCreated();

        $this->assertDatabaseMissing('kb_articles', [
            'id' => $response->json('data.id'),
            'published_at' => null,
        ]);
    }

    public function test_article_creation_requires_title_and_body(): void
    {
        $this->actingAsAgent();

        $this->postJson('/api/v1/kb/articles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_requester_cannot_choose_article_status_on_creation(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/kb/articles', [
            'title' => 'Umgehungsversuch',
            'body' => 'Dieser Artikel darf nicht direkt veröffentlicht werden.',
            'status' => ArticleStatus::Published->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseMissing('kb_articles', ['title' => 'Umgehungsversuch']);
    }

    public function test_slug_is_generated_from_title(): void
    {
        $this->actingAsAgent();

        $this->postJson('/api/v1/kb/articles', [
            'title' => 'Mein Test Artikel',
            'body' => 'Inhalt...',
        ])->assertCreated();

        $this->assertDatabaseHas('kb_articles', [
            'slug' => 'mein-test-artikel',
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────

    public function test_can_show_single_article(): void
    {
        $this->actingAsUser();
        $article = KbArticle::factory()->published()->create();

        $this->getJson("/api/v1/kb/articles/{$article->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'title', 'slug', 'body', 'status', 'author', 'tags']]);
    }

    // ─── Update ───────────────────────────────────────────────────

    public function test_agent_can_update_article(): void
    {
        $this->actingAsAgent();
        $article = KbArticle::factory()->draft()->create();

        $this->patchJson("/api/v1/kb/articles/{$article->id}", [
            'title' => 'Aktualisierter Titel',
            'body' => $article->body,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Aktualisierter Titel');
    }

    public function test_requester_cannot_publish_own_article_through_generic_update(): void
    {
        $author = $this->actingAsUser();
        $article = KbArticle::factory()->draft()->create(['author_id' => $author->id]);

        $this->patchJson("/api/v1/kb/articles/{$article->id}", [
            'status' => ArticleStatus::Published->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(ArticleStatus::Draft, $article->fresh()->status);
        $this->assertNull($article->fresh()->published_at);
    }

    public function test_staff_cannot_bypass_workflow_through_generic_update(): void
    {
        $this->actingAsAdmin();
        $article = KbArticle::factory()->draft()->create();

        $this->patchJson("/api/v1/kb/articles/{$article->id}", [
            'status' => ArticleStatus::Published->value,
            'published_at' => now()->toISOString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'published_at']);

        $this->assertSame(ArticleStatus::Draft, $article->fresh()->status);
        $this->assertNull($article->fresh()->published_at);
    }

    // ─── Destroy ──────────────────────────────────────────────────

    public function test_agent_can_delete_own_article(): void
    {
        $agent = $this->actingAsAgent();
        $article = KbArticle::factory()->create(['author_id' => $agent->id]);

        $this->deleteJson("/api/v1/kb/articles/{$article->id}")
            ->assertOk();

        $this->assertSoftDeleted('kb_articles', ['id' => $article->id]);
    }

    public function test_agent_cannot_delete_others_article(): void
    {
        $this->actingAsAgent();
        $article = KbArticle::factory()->create(); // Autor ist ein anderer, zufälliger User

        $this->deleteJson("/api/v1/kb/articles/{$article->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('kb_articles', ['id' => $article->id, 'deleted_at' => null]);
    }

    public function test_unauthenticated_user_cannot_access_kb(): void
    {
        $this->getJson('/api/v1/kb/articles')
            ->assertUnauthorized();
    }

    public function test_duplicate_slug_insert_throws_unique_constraint_violation(): void
    {
        // Validates the assumption that KbArticleService::createWithUniqueSlug()
        // relies on: a violation of the UNIQUE index on slug must throw
        // exactly this exception, so the retry mechanism reliably catches it.
        KbArticle::factory()->create(['slug' => 'mein-slug']);

        $this->expectException(UniqueConstraintViolationException::class);

        KbArticle::factory()->create(['slug' => 'mein-slug']);
    }

    public function test_author_can_update_own_submitted_article(): void
    {
        $author = $this->actingAsAgent();
        $article = KbArticle::factory()->create([
            'author_id' => $author->id,
            'status' => ArticleStatus::Submitted,
        ]);

        $response = $this->patchJson("/api/v1/kb/articles/{$article->id}", [
            'title' => 'Aktualisierter Titel',
            'body' => $article->body,
        ]);

        $response->assertOk();
        $this->assertSame('Aktualisierter Titel', $article->fresh()->title);
    }

    public function test_author_cannot_update_own_published_article(): void
    {
        // Deliberately a requester (not staff) as author — per the policy,
        // staff have edit rights at all times, regardless of status. Only a
        // non-staff author loses it after publication.
        $author = $this->createUser();
        $article = KbArticle::factory()->create([
            'author_id' => $author->id,
            'status' => ArticleStatus::Published,
        ]);

        Sanctum::actingAs($author);

        $response = $this->patchJson("/api/v1/kb/articles/{$article->id}", [
            'title' => 'Sollte nicht klappen',
            'body' => $article->body,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_add_addendum_to_published_article(): void
    {
        $this->actingAsAdmin();
        $article = KbArticle::factory()->create(['status' => ArticleStatus::Published]);

        $response = $this->postJson("/api/v1/kb/articles/{$article->id}/addendum", [
            'text' => 'Zusätzlicher Hinweis vom Staff.',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Zusätzlicher Hinweis vom Staff.', $article->fresh()->addendum);
    }

    public function test_author_can_add_addendum_to_own_published_article(): void
    {
        $author = $this->actingAsAgent();
        $article = KbArticle::factory()->create([
            'author_id' => $author->id,
            'status' => ArticleStatus::Published,
        ]);

        $response = $this->postJson("/api/v1/kb/articles/{$article->id}/addendum", [
            'text' => 'Nachtrag vom Autor selbst.',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Nachtrag vom Autor selbst.', $article->fresh()->addendum);
    }

    public function test_other_user_cannot_add_addendum(): void
    {
        $author = $this->createAgent();
        $article = KbArticle::factory()->create([
            'author_id' => $author->id,
            'status' => ArticleStatus::Published,
        ]);

        $this->actingAsUser();

        $response = $this->postJson("/api/v1/kb/articles/{$article->id}/addendum", [
            'text' => 'Sollte nicht klappen.',
        ]);

        $response->assertForbidden();
    }

    public function test_addenda_are_appended_not_overwritten(): void
    {
        $this->actingAsAdmin();
        $article = KbArticle::factory()->create(['status' => ArticleStatus::Published]);

        $this->postJson("/api/v1/kb/articles/{$article->id}/addendum", ['text' => 'Erster Nachtrag.'])->assertOk();
        $this->postJson("/api/v1/kb/articles/{$article->id}/addendum", ['text' => 'Zweiter Nachtrag.'])->assertOk();

        $addendum = $article->fresh()->addendum;
        $this->assertStringContainsString('Erster Nachtrag.', $addendum);
        $this->assertStringContainsString('Zweiter Nachtrag.', $addendum);
    }

    public function test_cannot_add_addendum_to_draft_article(): void
    {
        $this->actingAsAdmin();
        $article = KbArticle::factory()->create(['status' => ArticleStatus::Draft]);

        $response = $this->postJson("/api/v1/kb/articles/{$article->id}/addendum", [
            'text' => 'Sollte nicht klappen.',
        ]);

        $response->assertForbidden();
    }
}
