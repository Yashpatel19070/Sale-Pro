<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasAnyRole(['admin', 'manager']);
    }

    public function view(User $authUser, User $user): bool
    {
        if ($authUser->hasRole('admin')) {
            return true;
        }

        if ($authUser->hasRole('manager')) {
            return $authUser->department_id !== null
                && $authUser->department_id === $user->department_id;
        }

        return $authUser->id === $user->id;
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function update(User $authUser, User $user): bool
    {
        return $authUser->hasRole('admin') || $authUser->id === $user->id;
    }

    public function delete(User $authUser, User $user): bool
    {
        return $authUser->hasRole('admin') && $authUser->id !== $user->id;
    }

    public function restore(User $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function changeStatus(User $authUser, User $user): bool
    {
        return $authUser->hasRole('admin') && $authUser->id !== $user->id;
    }

    public function resetPassword(User $authUser, User $user): bool
    {
        return $authUser->hasRole('admin');
    }
}
