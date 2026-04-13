<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProductListing;
use App\Models\User;

class ProductListingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_VIEW_ANY);
    }

    public function view(User $user, ProductListing $listing): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_CREATE);
    }

    public function update(User $user, ProductListing $listing): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_EDIT);
    }

    public function delete(User $user, ProductListing $listing): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_DELETE);
    }

    public function restore(User $user): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_RESTORE);
    }
}
