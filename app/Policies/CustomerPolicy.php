<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CUSTOMERS_VIEW_ANY);
    }

    public function view(User $user, Customer $customer): bool
    {
        if (! $user->can(Permission::CUSTOMERS_VIEW)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $user->department_id !== null
                && $user->department_id === $customer->department_id;
        }

        return $customer->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CUSTOMERS_CREATE);
    }

    public function update(User $user, Customer $customer): bool
    {
        if (! $user->can(Permission::CUSTOMERS_EDIT)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $user->department_id !== null
                && $user->department_id === $customer->department_id;
        }

        return $customer->assigned_to === $user->id;
    }

    /**
     * Separate from update() — only admin and manager can change status.
     * Sales can edit assigned customers but CANNOT change their status.
     */
    public function changeStatus(User $user, Customer $customer): bool
    {
        if (! $user->can(Permission::CUSTOMERS_EDIT)) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function assign(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMERS_ASSIGN);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMERS_DELETE);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMERS_RESTORE);
    }
}
