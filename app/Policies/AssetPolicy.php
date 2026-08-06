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

    /** Liste selbst wird im Service gefiltert (Requester sieht nur zugewiesene Assets) */
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

    /** Anlegen — nur Admin/Agent */
    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    /** Bearbeiten — nur Admin/Agent, nicht der Requester, dem es zugewiesen ist */
    public function update(User $user, Asset $asset): bool
    {
        return $this->isStaff($user);
    }

    /** Zuweisen und Freigeben (dieselbe Ability, siehe AssetController::unassign()) */
    public function assign(User $user, Asset $asset): bool
    {
        return $this->isStaff($user);
    }

    /** Löschen — ausschließlich Admin, nicht Agent */
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

    /** Kategorien/Statuswerte verwalten — ausschließlich Admin */
    public function manageCategories(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
