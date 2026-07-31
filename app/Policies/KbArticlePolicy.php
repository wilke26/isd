<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ArticleStatus;
use App\Models\KbArticle;
use App\Models\User;

class KbArticlePolicy
{
    private function isStaff(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('agent');
    }

    private function isAuthor(User $user, KbArticle $article): bool
    {
        return $article->author_id === $user->id;
    }

    /** Liste selbst wird im Service gefiltert (published + eigene Artikel) */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KbArticle $article): bool
    {
        if ($article->status === ArticleStatus::Published) {
            return true;
        }

        return $this->isStaff($user) || $this->isAuthor($user, $article);
    }

    /** Jeder authentifizierte Benutzer darf einen Entwurf anlegen */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Bearbeiten: Staff jederzeit; Autor nur solange der Artikel noch Entwurf ist.
     * Nach dem Einreichen (submitted) oder der Veröffentlichung darf der Autor
     * nicht mehr eigenmächtig verändern.
     */
    public function update(User $user, KbArticle $article): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        return $this->isAuthor($user, $article) && $article->status === ArticleStatus::Draft;
    }

    /** Zur Prüfung einreichen — nur der Autor, nur aus dem Entwurfsstatus heraus */
    public function submit(User $user, KbArticle $article): bool
    {
        return $this->isAuthor($user, $article) && $article->status === ArticleStatus::Draft;
    }

    /** Veröffentlichen/Archivieren — ausschließlich Staff */
    public function publish(User $user, KbArticle $article): bool
    {
        return $this->isStaff($user);
    }

    public function archive(User $user, KbArticle $article): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Löschen: Admin immer; Agent nur eigene Artikel; Autor (Requester)
     * nur den eigenen, noch unveröffentlichten Entwurf.
     */
    public function delete(User $user, KbArticle $article): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('agent')) {
            return $this->isAuthor($user, $article);
        }

        return $this->isAuthor($user, $article) && $article->status === ArticleStatus::Draft;
    }
}
