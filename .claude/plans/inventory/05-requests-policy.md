# Inventory Module — Requests & Policy

## No FormRequests

This module is **read-only**. There is no user input beyond URL parameters (route model binding).
FormRequests are not needed.

---

## Permission Constants

Add to `app/Enums/Permission.php`:

```php
// Inventory (stock visibility — read only)
const INVENTORY_VIEW_ANY              = 'inventory.view-any';
const INVENTORY_VIEW_BY_SKU           = 'inventory.view-by-sku';
const INVENTORY_VIEW_BY_SKU_AT_LOCATION = 'inventory.view-by-sku-at-location';
```

---

## Policy

`app/Policies/InventoryPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class InventoryPolicy
{
    /**
     * Stock overview dashboard — all three roles can view.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::INVENTORY_VIEW_ANY);
    }

    /**
     * Stock by SKU drill-down — all three roles can view.
     */
    public function viewBySku(User $user): bool
    {
        return $user->can(Permission::INVENTORY_VIEW_BY_SKU);
    }

    /**
     * Serials for one SKU at one location — all three roles can view.
     */
    public function viewBySkuAtLocation(User $user): bool
    {
        return $user->can(Permission::INVENTORY_VIEW_BY_SKU_AT_LOCATION);
    }
}
```

---

## Policy Registration

In `app/Providers/AppServiceProvider.php`, register the policy inside `boot()`:

```php
use App\Models\InventorySerial;
use App\Policies\InventoryPolicy;

// Inside boot():
Gate::policy(InventorySerial::class, InventoryPolicy::class);
```

> The policy is registered against `InventorySerial::class` because there is no
> dedicated `Inventory` model. The controller calls `$this->authorize('viewAny', InventorySerial::class)`
> which Laravel resolves to `InventoryPolicy::viewAny()`.

---

## Permission Seeder

Create a dedicated `InventoryPermissionSeeder.php` (consistent with all other modules).

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;

class InventoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::INVENTORY_VIEW_ANY,
            Permission::INVENTORY_VIEW_BY_SKU,
            Permission::INVENTORY_VIEW_BY_SKU_AT_LOCATION,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions); // null-safe
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);
        Role::where('name', 'manager')->first()?->givePermissionTo($permissions);
        Role::where('name', 'sales')->first()?->givePermissionTo($permissions); // all read-only
    }
}
```

---

## Role Access Summary

| Permission constant | admin | manager | sales |
|--------------------|:-----:|:-------:|:-----:|
| `INVENTORY_VIEW_ANY` | ✅ | ✅ | ✅ |
| `INVENTORY_VIEW_BY_SKU` | ✅ | ✅ | ✅ |
| `INVENTORY_VIEW_BY_SKU_AT_LOCATION` | ✅ | ✅ | ✅ |

> `super-admin` bypasses all gate checks via `Gate::before()` — no explicit assignment needed.

---

## Blade Usage

Since all roles share the same view permissions, nav links can be shown unconditionally
to authenticated admin-side users. However, for consistency and future-proofing, gate them:

```blade
@can('inventory.view-any')
    <a href="{{ route('inventory.index') }}">Stock Dashboard</a>
@endcan
```
