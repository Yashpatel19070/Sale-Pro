<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::DEPARTMENTS_VIEW_ANY);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can(Permission::DEPARTMENTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::DEPARTMENTS_CREATE);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can(Permission::DEPARTMENTS_EDIT);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can(Permission::DEPARTMENTS_DELETE);
    }

    public function restore(User $user): bool
    {
        return $user->can(Permission::DEPARTMENTS_RESTORE);
    }
}
