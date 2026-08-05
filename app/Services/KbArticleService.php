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

        $article = $this->createWithUniqueSlug($author, $data, $status);

        if (! empty($data['tags'])) {
            $article->tags()->sync($data['tags']);
        }

        return $article->load(['author', 'category', 'tags']);
    }

    /**
     * uniqueSlug() prüft per SELECT, ob ein Slug frei ist — zwischen dieser
     * Prüfung und dem tatsächlichen INSERT liegt ein Zeitfenster, in dem ein
     * paralleler Request mit identischem Titel denselben Slug ebenfalls als
     * frei ansehen könnte (klassisches TOCTOU-Problem). Der DB-seitige
     * UNIQUE-Index auf kb_articles.slug verhindert dabei zuverlässig echte
     * Duplikate — ohne dieses Abfangen hier würde der zweite Request aber
     * mit einer ungefangenen 500-Antwort abbrechen, statt sauber mit einem
     * neuen Kandidaten weiterzumachen.
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
            // kb_articles hat aktuell nur einen UNIQUE-Index (slug) — jeder
            // hier auftretende Verstoß ist also eine Slug-Kollision durch
            // eine parallele Anfrage. Erneut versuchen: uniqueSlug() sieht
            // beim zweiten Durchlauf den inzwischen committeten Datensatz
            // der anderen Anfrage und wählt automatisch einen neuen Kandidaten.
            return $this->createWithUniqueSlug($author, $data, $status, $attempt + 1);
        }
    }

    public function update(KbArticle $article, array $data): KbArticle
    {
        return $this->updateWithUniqueSlug($article, $data);
    }

    /** Gleiches Race-Condition-Problem wie bei create(), gleiche Lösung. */
    private function updateWithUniqueSlug(KbArticle $article, array $data, int $attempt = 0): KbArticle
    {
        if ($attempt >= 5) {
            throw new \RuntimeException('Konnte nach mehreren Versuchen keinen eindeutigen Slug generieren.');
        }

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
     * Hängt eine zeitgestempelte Ergänzung an — additiv, der ursprüngliche
     * Haupttext (body) bleibt dabei unangetastet. Für Staff und den
     * ursprünglichen Autor gleichberechtigt nutzbar (siehe
     * KbArticlePolicy::addAddendum()), ausschließlich bei bereits
     * veröffentlichten oder archivierten Artikeln.
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
            'status' => ArticleStatus::Published,
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
