<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KbArticleService
{
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $isStaff = $user->hasRole('admin') || $user->hasRole('agent');

        return KbArticle::with(['author', 'category', 'tags'])
            ->when(! $isStaff, function ($q) use ($user) {
                // Non-staff see published articles as well as their own
                // (regardless of status — draft, submitted, archived).
                $q->where(function ($q2) use ($user) {
                    $q2->where('status', ArticleStatus::Published)
                        ->orWhere('author_id', $user->id);
                });
            })
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

    public function findOrFail(int $id): KbArticle
    {
        return KbArticle::with(['author', 'category', 'tags'])->findOrFail($id);
    }

    /**
     * Creates an article. Non-staff users can only create drafts — a direct
     * publish that bypasses the editorial workflow is not possible for
     * them, regardless of what is sent in the request.
     */
    public function create(User $author, array $data): KbArticle
    {
        $isStaff = $author->hasRole('admin') || $author->hasRole('agent');

        $status = $isStaff && isset($data['status'])
            ? ArticleStatus::from($data['status'])
            : ArticleStatus::Draft;

        $article = $this->createWithUniqueSlug($author, $data, $status);

        if (! empty($data['tags'])) {
            $article->tags()->sync($data['tags']);
        }

        return $article->load(['author', 'category', 'tags']);
    }

    /**
     * uniqueSlug() checks via SELECT whether a slug is free — between this
     * check and the actual INSERT there's a time window in which a parallel
     * request with an identical title could also see the same slug as free
     * (a classic TOCTOU problem). The DB-side UNIQUE index on
     * kb_articles.slug reliably prevents actual duplicates — but without
     * catching it here, the second request would fail with an uncaught 500
     * response instead of cleanly retrying with a new candidate.
     */
    private function createWithUniqueSlug(User $author, array $data, ArticleStatus $status, int $attempt = 0): KbArticle
    {
        if ($attempt >= 5) {
            throw new \RuntimeException('Konnte nach mehreren Versuchen keinen eindeutigen Slug generieren.');
        }

        try {
            return KbArticle::create([
                ...$data,
                'author_id' => $author->id,
                'slug' => $this->uniqueSlug($data['title']),
                'status' => $status,
                'published_at' => $status === ArticleStatus::Published ? now() : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // kb_articles currently has only one UNIQUE index (slug) — so any
            // violation occurring here is a slug collision caused by a
            // parallel request. Retry: on the second pass, uniqueSlug() sees
            // the by-then-committed record from the other request and
            // automatically picks a new candidate.
            return $this->createWithUniqueSlug($author, $data, $status, $attempt + 1);
        }
    }

    public function update(KbArticle $article, array $data): KbArticle
    {
        return $this->updateWithUniqueSlug($article, $data);
    }

    /** Same race-condition problem as in create(), same solution. */
    private function updateWithUniqueSlug(KbArticle $article, array $data, int $attempt = 0): KbArticle
    {
        if ($attempt >= 5) {
            throw new \RuntimeException('Konnte nach mehreren Versuchen keinen eindeutigen Slug generieren.');
        }

        // Status transitions and their timestamps belong exclusively to the
        // workflow methods below. Keep this invariant even if a future caller
        // forgets to validate its input before invoking the service.
        unset($data['status'], $data['published_at']);

        if (isset($data['title']) && $data['title'] !== $article->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $article->id);
        }

        try {
            $article->update($data);
        } catch (UniqueConstraintViolationException) {
            return $this->updateWithUniqueSlug($article, $data, $attempt + 1);
        }

        if (isset($data['tags'])) {
            $article->tags()->sync($data['tags']);
        }

        return $article->fresh(['author', 'category', 'tags']);
    }

    /**
     * Appends a timestamped addendum — additive, the original main text
     * (body) remains untouched. Usable equally by staff and the original
     * author (see KbArticlePolicy::addAddendum()), only for articles that
     * are already published or archived.
     */
    public function addAddendum(KbArticle $article, User $author, string $text): KbArticle
    {
        $entry = sprintf(
            "[%s — %s]\n%s",
            now()->format('d.m.Y H:i'),
            $author->name,
            trim($text),
        );

        $article->addendum = $article->addendum
            ? $article->addendum . "\n\n" . $entry
            : $entry;

        $article->save();

        return $article->fresh(['author', 'category', 'tags']);
    }

    /** Submit a draft for editorial review (only from draft status) */
    public function submit(KbArticle $article): KbArticle
    {
        if ($article->status !== ArticleStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Nur Entwürfe können zur Prüfung eingereicht werden.'],
            ]);
        }

        $article->update(['status' => ArticleStatus::Submitted]);

        return $article->fresh();
    }

    /** Publish an article (from submitted or draft, e.g. when staff is the author) */
    public function publish(KbArticle $article): KbArticle
    {
        if (in_array($article->status, [ArticleStatus::Published, ArticleStatus::Archived], true)) {
            throw ValidationException::withMessages([
                'status' => ['Artikel ist bereits veröffentlicht oder archiviert.'],
            ]);
        }

        $article->update([
            'status' => ArticleStatus::Published,
            'published_at' => now(),
        ]);

        return $article->fresh();
    }

    /** Archive a published article */
    public function archive(KbArticle $article): KbArticle
    {
        if ($article->status !== ArticleStatus::Published) {
            throw ValidationException::withMessages([
                'status' => ['Nur veröffentlichte Artikel können archiviert werden.'],
            ]);
        }

        $article->update(['status' => ArticleStatus::Archived]);

        return $article->fresh();
    }

    /** Make the slug unique: "title", "title-2", "title-3", ... */
    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (
            KbArticle::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
