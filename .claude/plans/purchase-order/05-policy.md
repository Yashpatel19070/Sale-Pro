# Purchase Order Module — Policy

## PurchaseOrderPolicy

```php
<?php
// app/Policies/PurchaseOrderPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\PoType;
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
        return $user->can(Permission::PURCHASE_ORDERS_CONFIRM);
    }

    public function cancel(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_CANCEL);
    }

    public function reopen(User $user, PurchaseOrder $po): bool
    {
        return $user->can(Permission::PURCHASE_ORDERS_REOPEN);
    }

    /**
     * Close a return PO — reuses PURCHASE_ORDERS_CANCEL permission.
     * Only valid when $po->type === PoType::Return.
     */
    public function close(User $user, PurchaseOrder $po): bool
    {
        return $po->type === PoType::Return
            && $user->can(Permission::PURCHASE_ORDERS_CANCEL);
    }
}
```

---

## Register in AppServiceProvider

```php
Gate::policy(\App\Models\PurchaseOrder::class, \App\Policies\PurchaseOrderPolicy::class);
```

---

## Permission Seeder

```php
<?php
// database/seeders/PurchaseOrderPermissionSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;

class PurchaseOrderPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = [
            Permission::PURCHASE_ORDERS_VIEW_ANY,
            Permission::PURCHASE_ORDERS_VIEW,
            Permission::PURCHASE_ORDERS_CREATE,
            Permission::PURCHASE_ORDERS_UPDATE,
            Permission::PURCHASE_ORDERS_CONFIRM,
            Permission::PURCHASE_ORDERS_CANCEL,
            Permission::PURCHASE_ORDERS_REOPEN,
        ];

        foreach ($allPermissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin  = Role::findByName('super-admin');
        $admin       = Role::findByName('admin');
        $manager     = Role::findByName('manager');
        $procurement = Role::findByName('procurement');

        // Super Admin, Admin, Manager — full access
        foreach ([$superAdmin, $admin, $manager] as $role) {
            $role->givePermissionTo($allPermissions);
        }

        // Procurement — can view, create, confirm (but not cancel or reopen)
        $procurement->givePermissionTo([
            Permission::PURCHASE_ORDERS_VIEW_ANY,
            Permission::PURCHASE_ORDERS_VIEW,
            Permission::PURCHASE_ORDERS_CREATE,
            Permission::PURCHASE_ORDERS_UPDATE,
            Permission::PURCHASE_ORDERS_CONFIRM,
        ]);
    }
}
```

---

## Role Access Summary

| Permission | super-admin | admin | manager | procurement | Others |
|------------|:-----------:|:-----:|:-------:|:-----------:|:------:|
| viewAny | ✅ | ✅ | ✅ | ✅ | ❌ |
| view | ✅ | ✅ | ✅ | ✅ | ❌ |
| create | ✅ | ✅ | ✅ | ✅ | ❌ |
| update | ✅ | ✅ | ✅ | ✅ | ❌ |
| confirm | ✅ | ✅ | ✅ | ✅ | ❌ |
| cancel | ✅ | ✅ | ✅ | ❌ | ❌ |
| reopen | ✅ | ✅ | ✅ | ❌ | ❌ |

---

## Notes

- `update` policy also checks `$po->isEditable()` — draft-only. Prevents PATCH on non-draft POs
  even if the user has the permission.
- `reopen` permission is a gate check only. The **manager vs super-admin** distinction for 3rd+
  reopen is enforced in `PurchaseOrderService::reopen()` via `$po->reopen_count >= 2` check,
  not in the policy — policies return boolean, not error messages.
- `cancel` and `reopen` not available to procurement — only manager and above.
