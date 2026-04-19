# Supplier Module — Policy

## SupplierPolicy

```php
<?php
// app/Policies/SupplierPolicy.php

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

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SUPPLIERS_RESTORE);
    }
}
```

---

## Register in AppServiceProvider

```php
// In app/Providers/AppServiceProvider.php boot() method — add:
Gate::policy(\App\Models\Supplier::class, \App\Policies\SupplierPolicy::class);
```

---

## Permission Seeder

```php
<?php
// database/seeders/SupplierPermissionSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;

class SupplierPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            Permission::SUPPLIERS_VIEW_ANY,
            Permission::SUPPLIERS_VIEW,
            Permission::SUPPLIERS_CREATE,
            Permission::SUPPLIERS_UPDATE,
            Permission::SUPPLIERS_DELETE,
            Permission::SUPPLIERS_RESTORE,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Role assignments
        $superAdmin   = Role::findByName('super-admin');
        $admin        = Role::findByName('admin');
        $manager      = Role::findByName('manager');
        $procurement  = Role::findByName('procurement');

        // Super Admin, Admin, Manager — full access
        foreach ([$superAdmin, $admin, $manager] as $role) {
            $role->givePermissionTo($permissions);
        }

        // Procurement — view only
        $procurement->givePermissionTo([
            Permission::SUPPLIERS_VIEW_ANY,
            Permission::SUPPLIERS_VIEW,
        ]);
    }
}
```

---

## Role Access Summary

| Permission | super-admin | admin | manager | procurement | All others |
|------------|:-----------:|:-----:|:-------:|:-----------:|:----------:|
| viewAny | ✅ | ✅ | ✅ | ✅ | ❌ |
| view | ✅ | ✅ | ✅ | ✅ | ❌ |
| create | ✅ | ✅ | ✅ | ❌ | ❌ |
| update | ✅ | ✅ | ✅ | ❌ | ❌ |
| delete (deactivate) | ✅ | ✅ | ✅ | ❌ | ❌ |
| restore | ✅ | ✅ | ✅ | ❌ | ❌ |

---

## Notes

- Policy methods map directly to `Permission` enum constants — no hardcoded strings.
- `procurement` role can list and view suppliers (needed for PO creation) but cannot mutate them.
- `delete` in policy = "deactivate" in UI/service — Laravel policy naming convention, not hard delete.
