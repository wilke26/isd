<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\KbArticle;
use App\Services\KbArticleService;
use Tests\TestCase;

class KbArticleServiceTest extends TestCase
{
    public function test_delete_soft_deletes_article(): void
    {
        $article = KbArticle::factory()->create();

        (new KbArticleService)->delete($article);

        $this->assertSoftDeleted('kb_articles', ['id' => $article->id]);
    }
}
