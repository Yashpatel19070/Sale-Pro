<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PRODUCTS_VIEW_ANY);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(Permission::PRODUCTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PRODUCTS_CREATE);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(Permission::PRODUCTS_EDIT);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can(Permission::PRODUCTS_DELETE);
    }

    public function restore(User $user): bool
    {
        return $user->can(Permission::PRODUCTS_RESTORE);
    }
}
