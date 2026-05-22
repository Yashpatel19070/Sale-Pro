# Customer Address Module — Policy

**File:** `app/Policies/CustomerAddressPolicy.php`

Authorization is permission-based via Spatie Laravel Permission.
Policy also enforces ownership — address must belong to the route-bound customer.

---

## Permissions Used

| Permission Constant | String Value | What it grants |
|--------------------|--------------|----------------|
| `Permission::CUSTOMER_ADDRESSES_VIEW_ANY` | `customer-addresses.view-any` | List all addresses for a customer |
| `Permission::CUSTOMER_ADDRESSES_VIEW` | `customer-addresses.view` | View a single address |
| `Permission::CUSTOMER_ADDRESSES_CREATE` | `customer-addresses.create` | Create a new address |
| `Permission::CUSTOMER_ADDRESSES_UPDATE` | `customer-addresses.update` | Edit an address |
| `Permission::CUSTOMER_ADDRESSES_DELETE` | `customer-addresses.delete` | Soft-delete an address |
| `Permission::CUSTOMER_ADDRESSES_SET_DEFAULT` | `customer-addresses.set-default` | Mark an address as default |

---

## Full Policy Code

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;

class CustomerAddressPolicy
{
    /**
     * List addresses for a customer — GET /customers/{customer}/addresses
     * Called with: $this->authorize('viewAny', [CustomerAddress::class, $customer])
     */
    public function viewAny(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_VIEW_ANY);
    }

    /**
     * View a single address
     * Called with: $this->authorize('view', [$address, $customer])
     */
    public function view(User $user, CustomerAddress $address, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_VIEW)
            && $address->customer_id === $customer->id;
    }

    /**
     * Show create form + store — GET/POST /customers/{customer}/addresses
     * Called with: $this->authorize('create', [CustomerAddress::class, $customer])
     */
    public function create(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_CREATE);
    }

    /**
     * Show edit form + update — GET/PUT /customers/{customer}/addresses/{address}
     * Called with: $this->authorize('update', [$address, $customer])
     */
    public function update(User $user, CustomerAddress $address, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_UPDATE)
            && $address->customer_id === $customer->id;
    }

    /**
     * Soft-delete — DELETE /customers/{customer}/addresses/{address}
     * Called with: $this->authorize('delete', [$address, $customer])
     */
    public function delete(User $user, CustomerAddress $address, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_DELETE)
            && $address->customer_id === $customer->id;
    }

    /**
     * Set as default — PATCH /customers/{customer}/addresses/{address}/default
     * Called with: $this->authorize('setDefault', [$address, $customer])
     */
    public function setDefault(User $user, CustomerAddress $address, Customer $customer): bool
    {
        return $user->can(Permission::CUSTOMER_ADDRESSES_SET_DEFAULT)
            && $address->customer_id === $customer->id;
    }
}
```

---

## How `authorize()` Arguments Map to Policy Method Signatures

Laravel resolves the policy from the first argument. When it is a class string (`CustomerAddress::class`),
no model instance is passed. When it is a model instance (`$address`), that model is passed as the second
argument. Additional array elements are passed as extra parameters.

| Controller call | Policy method signature |
|-----------------|------------------------|
| `authorize('viewAny', [CustomerAddress::class, $customer])` | `viewAny(User $user, Customer $customer)` |
| `authorize('create', [CustomerAddress::class, $customer])` | `create(User $user, Customer $customer)` |
| `authorize('view', [$address, $customer])` | `view(User $user, CustomerAddress $address, Customer $customer)` |
| `authorize('update', [$address, $customer])` | `update(User $user, CustomerAddress $address, Customer $customer)` |
| `authorize('delete', [$address, $customer])` | `delete(User $user, CustomerAddress $address, Customer $customer)` |
| `authorize('setDefault', [$address, $customer])` | `setDefault(User $user, CustomerAddress $address, Customer $customer)` |

---

## Ownership Check

`$address->customer_id === $customer->id` is checked inside every address-scoped policy method.
This prevents an admin from accessing an address from a different customer by crafting a URL like
`/customers/1/addresses/99` where address `99` belongs to customer `2`.

---

## Policy Registration

Laravel auto-discovers policies in `app/Policies/` that follow the naming convention `{Model}Policy`.
No manual registration needed in `AppServiceProvider` if auto-discovery is enabled (default in Laravel 10+).

To verify: `php artisan route:list` should show policy bound to `CustomerAddress` model.

---

## Notes

- Policy methods return `bool` only — never throw exceptions
- `viewAny` and `create` do not receive an address model (none exists yet at that point)
- Ownership check (`$address->customer_id === $customer->id`) uses strict `===` — both are integers
- `view` is defined but not used by the current controller (no `show` action) — kept for completeness and future use
