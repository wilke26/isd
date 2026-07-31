<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KbArticleService
{
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $isStaff = $user->hasRole('admin') || $user->hasRole('agent');

        return KbArticle::with(['author', 'category', 'tags'])
            ->when(! $isStaff, function ($q) use ($user) {
                // Nicht-Staff sieht veröffentlichte Artikel sowie die eigenen
                // (unabhängig vom Status — Entwurf, eingereicht, archiviert).
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
     * Erstellt einen Artikel. Nicht-Staff-Benutzer können ausschließlich Entwürfe
     * anlegen — ein direktes Veröffentlichen am Redaktionsworkflow vorbei ist
     * für sie nicht möglich, unabhängig davon, was im Request mitgeschickt wird.
     */
    public function create(User $author, array $data): KbArticle
    {
        $isStaff = $author->hasRole('admin') || $author->hasRole('agent');

        $status = $isStaff && isset($data['status'])
            ? ArticleStatus::from($data['status'])
            : ArticleStatus::Draft;

        $article = KbArticle::create([
            ...$data,
            'author_id'    => $author->id,
            'slug'         => $this->uniqueSlug($data['title']),
            'status'       => $status,
            'published_at' => $status === ArticleStatus::Published ? now() : null,
        ]);

        if (! empty($data['tags'])) {
            $article->tags()->sync($data['tags']);
        }

        return $article->load(['author', 'category', 'tags']);
    }

    public function update(KbArticle $article, array $data): KbArticle
    {
        if (isset($data['title']) && $data['title'] !== $article->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $article->id);
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

    /** Entwurf zur redaktionellen Prüfung einreichen (nur aus dem Draft-Status) */
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

    /** Artikel veröffentlichen (aus submitted oder draft, z.B. bei Staff-Eigenautorschaft) */
    public function publish(KbArticle $article): KbArticle
    {
        if (in_array($article->status, [ArticleStatus::Published, ArticleStatus::Archived], true)) {
            throw ValidationException::withMessages([
                'status' => ['Artikel ist bereits veröffentlicht oder archiviert.'],
            ]);
        }

        $article->update([
            'status'       => ArticleStatus::Published,
            'published_at' => now(),
        ]);

        return $article->fresh();
    }

    /** Veröffentlichten Artikel archivieren */
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

    /** Slug eindeutig machen: "titel", "titel-2", "titel-3", ... */
    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i    = 2;

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
