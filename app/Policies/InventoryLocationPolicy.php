<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InventoryLocation;
use App\Models\User;

class InventoryLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_VIEW_ANY);
    }

    public function view(User $user, InventoryLocation $location): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_CREATE);
    }

    public function update(User $user, InventoryLocation $location): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_EDIT);
    }

    public function delete(User $user, InventoryLocation $location): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_DELETE);
    }

    public function restore(User $user, InventoryLocation $location): bool
    {
        return $user->can(Permission::INVENTORY_LOCATIONS_RESTORE);
    }
}
