<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InventoryMovement;
use App\Models\User;

class InventoryMovementPolicy
{
    /**
     * View the movement history list — admin, manager, sales.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::INVENTORY_MOVEMENTS_VIEW);
    }

    /**
     * View a single movement record.
     */
    public function view(User $user, InventoryMovement $movement): bool
    {
        return $user->can(Permission::INVENTORY_MOVEMENTS_VIEW);
    }

    /**
     * Access the create form — any user who can transfer, sell, or adjust.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::INVENTORY_MOVEMENTS_TRANSFER)
            || $user->can(Permission::INVENTORY_MOVEMENTS_SELL)
            || $user->can(Permission::INVENTORY_MOVEMENTS_ADJUST);
    }

    /**
     * Update — NEVER allowed. Movements are immutable.
     */
    public function update(User $user, InventoryMovement $movement): bool
    {
        return false;
    }

    /**
     * Delete — NEVER allowed. Movements are immutable.
     */
    public function delete(User $user, InventoryMovement $movement): bool
    {
        return false;
    }
}
