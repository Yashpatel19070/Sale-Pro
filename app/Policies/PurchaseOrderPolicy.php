<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_VIEW_ANY);
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_CREATE);
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_UPDATE);
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_DELETE);
    }

    public function restore(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_RESTORE);
    }

    public function submit(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_SUBMIT);
    }

    public function approve(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_APPROVE);
    }

    public function reject(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_REJECT);
    }

    public function cancel(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_CANCEL);
    }

    public function markOnTheWay(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_UPDATE);
    }

    public function qualityCheck(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_QUALITY_CHECK);
    }
}
