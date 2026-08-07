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

    /** The list itself is filtered in the service (requester sees only assigned assets) */
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

    /** Create — admin/agent only */
    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    /** Edit — admin/agent only, not the requester it's assigned to */
    public function update(User $user, Asset $asset): bool
    {
        return $this->isStaff($user);
    }

    /** Assign and release (same ability, see AssetController::unassign()) */
    public function assign(User $user, Asset $asset): bool
    {
        return $this->isStaff($user);
    }

    /** Delete — admin only, not agent */
    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * The assignment history also shows previous owners by name — this is
     * stricter than the plain "view" check on the currently assigned asset.
     * A requester the asset is currently assigned to may view it, but may
     * not find out who had it before. Own ability instead of reusing
     * 'view'.
     */
    public function viewHistory(User $user, Asset $asset): bool
    {
        return $this->isStaff($user);
    }

    /** Manage categories/status values — admin only */
    public function manageCategories(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
