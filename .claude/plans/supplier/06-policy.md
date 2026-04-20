# Supplier Module — Policy

**File:** `app/Policies/SupplierPolicy.php`

Authorization is permission-based using Spatie Laravel Permission.
Policy checks `$user->can('permission.name')` — no role checks directly.

---

## Permissions Used

| Permission | What it grants |
|------------|---------------|
| `suppliers.viewAny` | List all suppliers |
| `suppliers.view` | View a single supplier |
| `suppliers.create` | Create a new supplier |
| `suppliers.update` | Edit a supplier |
| `suppliers.delete` | Soft-delete a supplier |
| `suppliers.changeStatus` | Change supplier status |

---

## Full Policy Code

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    /**
     * List suppliers — GET /suppliers
     */
    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.viewAny');
    }

    /**
     * View a single supplier — GET /suppliers/{supplier}
     */
    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.view');
    }

    /**
     * Show create form + store — GET/POST /suppliers/create
     */
    public function create(User $user): bool
    {
        return $user->can('suppliers.create');
    }

    /**
     * Show edit form + update — GET/PUT /suppliers/{supplier}/edit
     */
    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.update');
    }

    /**
     * Soft-delete — DELETE /suppliers/{supplier}
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.delete');
    }

    /**
     * Change status — PATCH /suppliers/{supplier}/status
     */
    public function changeStatus(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.changeStatus');
    }
}
```

---

## Registering the Policy

If the project uses auto-discovery (Laravel 10+), the policy is auto-detected.
If `AuthServiceProvider.php` exists with a `$policies` array, add:

```php
use App\Models\Supplier;
use App\Policies\SupplierPolicy;

protected $policies = [
    Supplier::class => SupplierPolicy::class,
];
```

---

## How Controller Uses Policy

```php
// In SupplierController — exact authorize() calls:
$this->authorize('viewAny', Supplier::class);   // index
$this->authorize('create', Supplier::class);    // create, store
$this->authorize('view', $supplier);            // show
$this->authorize('update', $supplier);          // edit, update
$this->authorize('delete', $supplier);          // destroy
$this->authorize('changeStatus', $supplier);    // changeStatus
```

---

## Notes
- Policy methods return `bool` only — no exceptions thrown from policy
- `$supplier` parameter in `view`, `update`, `delete`, `changeStatus` is the route-bound model
- Super Admin role has all permissions assigned via seeder (see 09-permissions.md)
- Sales role only gets `suppliers.viewAny` and `suppliers.view`
- Manager role gets all except `suppliers.delete`
