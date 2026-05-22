<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;

class CustomerAddressPolicy
{
    public function viewAny(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_VIEW_ANY);
    }

    public function view(User $user, CustomerAddress $address, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_VIEW)
            && $address->customer_id === $customer->id;
    }

    public function create(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_CREATE);
    }

    public function update(User $user, CustomerAddress $address, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_UPDATE)
            && $address->customer_id === $customer->id;
    }

    public function delete(User $user, CustomerAddress $address, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_DELETE)
            && $address->customer_id === $customer->id;
    }

    public function setDefault(User $user, CustomerAddress $address, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_SET_DEFAULT)
            && $address->customer_id === $customer->id;
    }
}
