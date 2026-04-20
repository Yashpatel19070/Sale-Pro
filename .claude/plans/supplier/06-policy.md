# Supplier Module — Policy

**File:** `app/Policies/SupplierPolicy.php`

Authorization is permission-based using Spatie Laravel Permission.
Policy uses `Permission::SUPPLIERS_*` constants — never raw permission strings.

---

## Permissions Used

| Permission constant | String value | What it grants |
|---------------------|-------------|---------------|
| `Permission::SUPPLIERS_VIEW_ANY` | `suppliers.viewAny` | List all suppliers |
| `Permission::SUPPLIERS_VIEW` | `suppliers.view` | View a single supplier |
| `Permission::SUPPLIERS_CREATE` | `suppliers.create` | Create a new supplier |
| `Permission::SUPPLIERS_UPDATE` | `suppliers.update` | Edit a supplier |
| `Permission::SUPPLIERS_DELETE` | `suppliers.delete` | Soft-delete a supplier |
| `Permission::SUPPLIERS_RESTORE` | `suppliers.restore` | Restore a soft-deleted supplier |
| `Permission::SUPPLIERS_CHANGE_STATUS` | `suppliers.changeStatus` | Change supplier status |

---

## Full Policy Code

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SUPPLIERS_VIEW_ANY);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SUPPLIERS_CREATE);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_UPDATE);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_DELETE);
    }

    public function changeStatus(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_CHANGE_STATUS);
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_RESTORE);
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
// In SupplierController — authorize() calls:
$this->authorize('viewAny', Supplier::class);   // index
$this->authorize('create', Supplier::class);    // create
$this->authorize('view', $supplier);            // show
$this->authorize('update', $supplier);          // edit
$this->authorize('delete', $supplier);          // destroy
$this->authorize('restore', $supplier);         // restore
$this->authorize('changeStatus', $supplier);    // changeStatus
```

Note: `store` and `update` are gated by their FormRequest `authorize()` — the controller does **not** call `$this->authorize()` for those actions.

---

## Notes
- Policy methods use `Permission::SUPPLIERS_*` constants — never raw strings
- Policy methods return `bool` only — no exceptions thrown from policy
- `$supplier` parameter in `view`, `update`, `delete`, `changeStatus`, `restore` is the route-bound model
- Super Admin and Admin roles have all 7 permissions assigned via seeder (see 09-permissions.md)
- Sales role only gets `suppliers.viewAny` and `suppliers.view`
- Manager role gets all except `suppliers.delete` and `suppliers.restore`
