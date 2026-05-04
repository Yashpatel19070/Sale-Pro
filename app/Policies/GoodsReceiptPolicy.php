<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::GOODS_RECEIPTS_VIEW_ANY);
    }

    public function view(User $user, GoodsReceipt $grn): bool
    {
        return $user->can(Permission::GOODS_RECEIPTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::GOODS_RECEIPTS_CREATE);
    }

    public function update(User $user, GoodsReceipt $grn): bool
    {
        return $user->can(Permission::GOODS_RECEIPTS_UPDATE);
    }

    public function delete(User $user, GoodsReceipt $grn): bool
    {
        return $user->can(Permission::GOODS_RECEIPTS_DELETE);
    }
}
