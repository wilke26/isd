<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    // ─── Store ────────────────────────────────────────────────────

    public function test_agent_can_create_draft_article(): void
    {
        $agent    = $this->actingAsAgent();
        $category = KbCategory::factory()->create();

        $response = $this->postJson('/api/v1/kb/articles', [
            'title'       => 'Neuer Artikel',
            'body'        => 'Inhalt des Artikels...',
            'status'      => 'draft',
            'category_id' => $category->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Neuer Artikel')
            ->assertJsonPath('status.value', 'draft')
            ->assertJsonPath('author.id', $agent->id);
    }

    public function test_publishing_article_sets_published_at(): void
    {
        $this->actingAsAgent();

        $response = $this->postJson('/api/v1/kb/articles', [
            'title'  => 'Veröffentlichter Artikel',
            'body'   => 'Inhalt...',
            'status' => 'published',
        ]);

        $response->assertCreated();

        $this->assertDatabaseMissing('kb_articles', [
            'id'           => $response->json('id'),
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

    public function test_slug_is_generated_from_title(): void
    {
        $this->actingAsAgent();

        $this->postJson('/api/v1/kb/articles', [
            'title' => 'Mein Test Artikel',
            'body'  => 'Inhalt...',
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
            'body'  => $article->body,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Aktualisierter Titel');
    }

    // ─── Destroy ──────────────────────────────────────────────────

    public function test_agent_can_delete_own_article(): void
    {
        $agent   = $this->actingAsAgent();
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
}
