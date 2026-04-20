<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\PoStatus;
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
        return $user->can(Permission::PURCHASE_ORDERS_UPDATE) && $po->isEditable();
    }

    public function confirm(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_CONFIRM)
            && $po->status === PoStatus::Draft;
    }

    public function cancel(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_CANCEL)
            && in_array($po->status, [PoStatus::Draft, PoStatus::Open], true);
    }

    public function reopen(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_REOPEN)
            && $po->status === PoStatus::Closed;
    }

    public function close(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_CANCEL)
            && $po->status === PoStatus::Open;
    }
}
