<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    private function isStaff(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('agent');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Asset $asset): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        return $asset->assignments()
            ->where('user_id', $user->id)
            ->whereNull('returned_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->isStaff($user);
    }

    public function assign(User $user, Asset $asset): bool
    {
        return $this->isStaff($user);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Die Zuweisungshistorie zeigt auch frühere Besitzer namentlich — das ist
     * strenger als der reine "view"-Check auf das aktuell zugewiesene Asset.
     * Ein Requester, dem das Asset gerade zugewiesen ist, darf es zwar
     * ansehen, aber nicht erfahren, wer es vorher hatte. Eigene Ability
     * statt Wiederverwendung von 'view'.
     */
    public function viewHistory(User $user, Asset $asset): bool
    {
        return $this->isStaff($user);
    }

    public function manageCategories(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
