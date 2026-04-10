<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
