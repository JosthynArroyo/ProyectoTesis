<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can manage the target user.
     */
    public function manage(User $currentUser, User $targetUser): bool
    {
        if ($currentUser->id === $targetUser->id) {
            return false;
        }

        if ($currentUser->hasRole('superadmin')) {
            return ! $targetUser->hasRole('superadmin');
        }

        if ($currentUser->hasRole('administrador')) {
            return ! $targetUser->hasRole('administrador') && ! $targetUser->hasRole('superadmin');
        }

        return false;
    }
}
