<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\License;
use App\Models\User;

class LicensePolicy
{
    private function isStaff(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('agent');
    }

    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, License $license): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(User $user, License $license): bool
    {
        return $this->isStaff($user);
    }

    public function assign(User $user, License $license): bool
    {
        return $this->isStaff($user);
    }

    public function delete(User $user, License $license): bool
    {
        return $user->hasRole('admin') && ! $license->assignments()->exists();
    }

    public function restore(User $user, License $license): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, License $license): bool
    {
        return $user->hasRole('admin') && ! $license->assignments()->exists();
    }
}
