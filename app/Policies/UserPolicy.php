<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function view(User $user, User $record): bool
    {
        return $this->canManageUsers($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function update(User $user, User $record): bool
    {
        return $this->canManageUsers($user);
    }

    public function delete(User $user, User $record): bool
    {
        if (! $this->canManageUsers($user) || $user->is($record)) {
            return false;
        }

        if ($record->hasRole('administrador')) {
            return User::role('administrador')->count() > 1;
        }

        return true;
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    private function canManageUsers(User $user): bool
    {
        return $user->hasRole('administrador') && $user->can('gestionar_usuarios');
    }
}
