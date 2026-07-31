<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Class KbArticleService
 *
 * Provides business logic for managing knowledge base articles.
 */
class KbArticleService
{
    /**
     * List knowledge base articles with optional filters.
     *
     * @param User $user
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator
     */
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $canSeeDrafts = $user->hasRole('admin') || $user->hasRole('agent');

        return KbArticle::with(['author', 'category', 'tags'])
            ->when(! $canSeeDrafts, fn ($q) => $q->where('status', ArticleStatus::Published))
            ->when(isset($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['tag']), fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('slug', $filters['tag'])))
            ->when(isset($filters['search']), fn ($q) => $q->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('body', 'like', '%' . $filters['search'] . '%');
            }))
            ->latest('published_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Find an article by its ID or throw a ModelNotFoundException.
     *
     * @param int $id
     * @return KbArticle
     */
    public function findOrFail(int $id): KbArticle
    {
        return KbArticle::with(['author', 'category', 'tags'])->findOrFail($id);
    }

    /**
     * Create a new knowledge base article.
     *
     * @param User $author
     * @param array<string, mixed> $data
     * @return KbArticle
     */
    public function create(User $author, array $data): KbArticle
    {
        $article = KbArticle::create([
            ...$data,
            'status'       => $data['status'] ?? ArticleStatus::Draft->value,  // ← neu
            'author_id'    => $author->id,
            'slug'         => Str::slug($data['title']),
            'published_at' => ($data['status'] ?? '') === ArticleStatus::Published->value ? now() : null,
        ]);
        if (! empty($data['tags'])) {
            $article->tags()->sync($data['tags']);
        }

        return $article->load(['author', 'category', 'tags']);
    }

    /**
     * Update an existing knowledge base article.
     *
     * @param KbArticle $article
     * @param array<string, mixed> $data
     * @return KbArticle
     */
    public function update(KbArticle $article, array $data): KbArticle
    {
        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        if (
            isset($data['status'])
            && $data['status'] === ArticleStatus::Published->value
            && $article->status !== ArticleStatus::Published
        ) {
            $data['published_at'] = now();
        }

        $article->update($data);

        if (isset($data['tags'])) {
            $article->tags()->sync($data['tags']);
        }

        return $article->fresh(['author', 'category', 'tags']);
    }
}
