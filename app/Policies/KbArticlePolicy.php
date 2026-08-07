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

    /** The list itself is filtered in the service (published + own articles) */
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

    /** Any authenticated user may create a draft */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Edit: staff at any time; author as long as the article is not yet
     * published (draft OR already submitted — before publication there's
     * nothing whose stability would need protecting, direct editing is the
     * simplest approach here). After publication, addAddendum() applies
     * instead — the main text then stays stable, addenda are added
     * additively.
     */
    public function update(User $user, KbArticle $article): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        return $this->isAuthor($user, $article)
            && in_array($article->status, [ArticleStatus::Draft, ArticleStatus::Submitted], true);
    }

    /** Submit for review — author only, only from draft status */
    public function submit(User $user, KbArticle $article): bool
    {
        return $this->isAuthor($user, $article) && $article->status === ArticleStatus::Draft;
    }

    /** Publish/archive — staff only */
    public function publish(User $user, KbArticle $article): bool
    {
        return $this->isStaff($user);
    }

    public function archive(User $user, KbArticle $article): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Append a timestamped addendum — staff and the original author are
     * treated equally, but only for articles that are already published or
     * archived. For drafts/submitted articles, the regular edit right from
     * update() applies instead — an additive addendum would be redundant
     * there, since direct editing is still possible.
     */
    public function addAddendum(User $user, KbArticle $article): bool
    {
        if (! in_array($article->status, [ArticleStatus::Published, ArticleStatus::Archived], true)) {
            return false;
        }

        return $this->isStaff($user) || $this->isAuthor($user, $article);
    }

    /**
     * Delete: admin always; agent only their own articles; author
     * (requester) only their own, still-unpublished draft.
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
