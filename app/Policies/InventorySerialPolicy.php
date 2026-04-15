<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InventorySerial;
use App\Models\User;

class InventorySerialPolicy
{
    /** List serials — admin, manager, sales. */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_VIEW_ANY);
    }

    /** View a single serial — admin, manager, sales. */
    public function view(User $user, InventorySerial $serial): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_VIEW);
    }

    /** Receive (create) a new serial — admin, manager, sales. */
    public function create(User $user): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_CREATE);
    }

    /** Edit notes / supplier_name — admin, manager, sales. */
    public function update(User $user, InventorySerial $serial): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_EDIT);
    }

    /** Mark as damaged — admin, manager only (not sales). */
    public function markDamaged(User $user, InventorySerial $serial): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_MARK_DAMAGED);
    }

    /** Mark as missing — admin, manager only (not sales). */
    public function markMissing(User $user, InventorySerial $serial): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_MARK_MISSING);
    }

    /**
     * View purchase price — admin and manager only (internal cost data, hidden from sales).
     */
    public function viewPurchasePrice(User $user, InventorySerial $serial): bool
    {
        return $user->can(Permission::INVENTORY_SERIALS_VIEW_PURCHASE_PRICE);
    }

    // ── Inventory stock-visibility gates (used by InventoryController) ────────

    /** Stock overview dashboard — admin, manager, sales. */
    public function inventoryViewAny(User $user): bool
    {
        return $user->can(Permission::INVENTORY_VIEW_ANY);
    }

    /** Stock by SKU drill-down — admin, manager, sales. */
    public function inventoryViewBySku(User $user): bool
    {
        return $user->can(Permission::INVENTORY_VIEW_BY_SKU);
    }

    /** Serials for one SKU at one location — admin, manager, sales. */
    public function inventoryViewBySkuAtLocation(User $user): bool
    {
        return $user->can(Permission::INVENTORY_VIEW_BY_SKU_AT_LOCATION);
    }
}
