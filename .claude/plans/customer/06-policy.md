# Customer Module — Policy

**File:** `app/Policies/CustomerPolicy.php`

Authorization is permission-based using Spatie Laravel Permission.
Policy checks `$user->can('permission.name')` — no role checks directly.

---

## Permissions Used

| Permission | What it grants |
|------------|---------------|
| `customers.viewAny` | List all customers |
| `customers.view` | View a single customer |
| `customers.create` | Create a new customer |
| `customers.update` | Edit a customer |
| `customers.delete` | Soft-delete a customer |
| `customers.changeStatus` | Change customer status |

---

## Full Policy Code

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * List customers — GET /customers
     */
    public function viewAny(User $user): bool
    {
        return $user->can('customers.viewAny');
    }

    /**
     * View a single customer — GET /customers/{customer}
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }

    /**
     * Show create form + store — GET/POST /customers/create
     */
    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    /**
     * Show edit form + update — GET/PUT /customers/{customer}/edit
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update');
    }

    /**
     * Soft-delete — DELETE /customers/{customer}
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete');
    }

    /**
     * Change status — PATCH /customers/{customer}/status
     */
    public function changeStatus(User $user, Customer $customer): bool
    {
        return $user->can('customers.changeStatus');
    }
}
```

---

## Registering the Policy

Add to `app/Providers/AuthServiceProvider.php` in the `$policies` array:

```php
use App\Models\Customer;
use App\Policies\CustomerPolicy;

protected $policies = [
    Customer::class => CustomerPolicy::class,
];
```

If the project uses auto-discovery (Laravel 10+), the policy may be auto-detected.
Check if `AuthServiceProvider.php` exists and has a `$policies` array — if yes, add the entry.
If the project uses `AppServiceProvider` for policy registration, add it there instead.

---

## How Controller Uses Policy

```php
// In CustomerController — these are the exact authorize() calls:
$this->authorize('viewAny', Customer::class);   // index
$this->authorize('create', Customer::class);    // create, store
$this->authorize('view', $customer);            // show
$this->authorize('update', $customer);          // edit, update
$this->authorize('delete', $customer);          // destroy
$this->authorize('changeStatus', $customer);    // changeStatus
```

---

## Notes
- Policy methods return `bool` only — no exceptions thrown from policy
- `$customer` parameter in `view`, `update`, `delete`, `changeStatus` is the route-bound model
- Super Admin role should have all permissions assigned via seeder (see 09-seeder.md)
- Staff role only gets `customers.viewAny` and `customers.view`
