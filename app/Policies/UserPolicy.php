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
        if ($currentUser->hasRole('superadmin')) {
            return $this->isOnlyRole($targetUser, 'administrador');
        }

        if ($currentUser->hasRole('administrador')) {
            return $this->isOnlyOneOf($targetUser, ['doctor', 'paciente', 'laboratorio']);
        }

        return false;
    }

    private function isOnlyRole(User $user, string $role): bool
    {
        $user->loadMissing('roles');

        return $user->roles->count() === 1 && $user->roles->contains('name', $role);
    }

    private function isOnlyOneOf(User $user, array $roles): bool
    {
        $user->loadMissing('roles');

        if ($user->roles->count() !== 1) {
            return false;
        }

        return $user->roles->contains(fn ($role) => in_array($role->name, $roles, true));
    }
}
