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

        // Requester darf nur ein ihm aktuell zugewiesenes Asset einsehen
        return $asset->assignments()
            ->where('user_id', $user->id)
            ->whereNull('returned_at')
            ->exists();
    }

    /** Anlegen, Bearbeiten, Zuweisen/Zurücknehmen — Admin und Agent, operatives Tagesgeschäft */
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

    /** Löschen ist ausschließlich dem Admin vorbehalten (destruktiver Vorgang) */
    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin');
    }

    /** Kategorien und Statuswerte sind Stammdaten — ausschließlich Admin */
    public function manageCategories(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
