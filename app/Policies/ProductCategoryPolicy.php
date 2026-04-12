<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_VIEW_ANY);
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_CREATE);
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_UPDATE);
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_DELETE);
    }
}
