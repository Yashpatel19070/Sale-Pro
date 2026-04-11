<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->can(Permission::USERS_VIEW_ANY);
    }

    public function view(User $authUser, User $user): bool
    {
        if (! $authUser->can(Permission::USERS_VIEW)) {
            return false;
        }

        if ($authUser->hasRole('admin')) {
            return true;
        }

        if ($authUser->hasRole('manager')) {
            return $authUser->department_id !== null
                && $authUser->department_id === $user->department_id;
        }

        // sales — self only
        return $authUser->id === $user->id;
    }

    public function create(User $authUser): bool
    {
        return $authUser->can(Permission::USERS_CREATE);
    }

    public function update(User $authUser, User $user): bool
    {
        if (! $authUser->can(Permission::USERS_EDIT)) {
            return false;
        }

        // admin can edit anyone; sales limited to self
        return $authUser->hasRole('admin') || $authUser->id === $user->id;
    }

    public function delete(User $authUser, User $user): bool
    {
        return $authUser->can(Permission::USERS_DELETE) && $authUser->id !== $user->id;
    }

    public function restore(User $authUser): bool
    {
        return $authUser->can(Permission::USERS_RESTORE);
    }

    public function changeStatus(User $authUser, User $user): bool
    {
        return $authUser->can(Permission::USERS_CHANGE_STATUS) && $authUser->id !== $user->id;
    }

    public function resetPassword(User $authUser, User $user): bool
    {
        return $authUser->can(Permission::USERS_RESET_PASSWORD);
    }
}
