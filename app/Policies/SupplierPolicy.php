<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SUPPLIERS_VIEW_ANY);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SUPPLIERS_CREATE);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_UPDATE);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_DELETE);
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_RESTORE);
    }
}
