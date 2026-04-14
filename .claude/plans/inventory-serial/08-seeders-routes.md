# InventorySerial — Seeders & Routes

## InventorySerialPermissionSeeder.php

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InventorySerialPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::INVENTORY_SERIALS_VIEW_ANY,
            Permission::INVENTORY_SERIALS_VIEW,
            Permission::INVENTORY_SERIALS_CREATE,
            Permission::INVENTORY_SERIALS_EDIT,
            Permission::INVENTORY_SERIALS_MARK_DAMAGED,
            Permission::INVENTORY_SERIALS_MARK_MISSING,
        ];

        // Create all permission records
        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions); // future role, null-safe
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);
        Role::where('name', 'manager')->first()?->givePermissionTo($permissions);
        Role::where('name', 'sales')->first()?->givePermissionTo([
            Permission::INVENTORY_SERIALS_VIEW_ANY,
            Permission::INVENTORY_SERIALS_VIEW,
            Permission::INVENTORY_SERIALS_CREATE,
        ]);
    }
}
```

**File path:** `database/seeders/InventorySerialPermissionSeeder.php`

---

## InventorySerialSeeder.php

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySerialSeeder extends Seeder
{
    public function run(): void
    {
        // Requires: ProductSeeder, InventoryLocationSeeder, and at least one admin user.
        $products  = Product::active()->inRandomOrder()->limit(5)->get();
        $locations = InventoryLocation::where('is_active', true)->get();
        $admin     = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::factory()->create()->assignRole('admin');

        if ($products->isEmpty() || $locations->isEmpty()) {
            $this->command->warn('InventorySerialSeeder: No products or locations found — skipping.');
            return;
        }

        foreach ($products as $product) {
            $location = $locations->random();

            // 3 in-stock serials per product
            InventorySerial::factory()
                ->count(3)
                ->forProduct($product)
                ->atLocation($location)
                ->receivedBy($admin)
                ->create();

            // 1 sold
            InventorySerial::factory()
                ->sold()
                ->forProduct($product)
                ->receivedBy($admin)
                ->create();

            // 1 damaged
            InventorySerial::factory()
                ->damaged()
                ->forProduct($product)
                ->receivedBy($admin)
                ->create();
        }

        $this->command->info('InventorySerialSeeder: Created ' . InventorySerial::count() . ' serials.');
    }
}
```

**File path:** `database/seeders/InventorySerialSeeder.php`

---

## DatabaseSeeder.php — Additions

Add the following calls to `database/seeders/DatabaseSeeder.php` after `InventoryLocationSeeder::class`:

```php
// Add to DatabaseSeeder.php after InventoryLocationSeeder::class:
InventorySerialPermissionSeeder::class,
InventorySerialSeeder::class,
// inventory-movement and inventory seeders follow in their own modules
```

The permission seeder must run **before** the data seeder.
Both must run **after** `RoleSeeder`, `ProductSeeder`, and the inventory-location seeders.

---

## Routes

Add the following block to `routes/web.php` inside the admin authenticated middleware group,
after the existing product-listings routes:

```php
use App\Http\Controllers\InventorySerialController;

// Inventory Serials
Route::prefix('inventory-serials')->name('inventory-serials.')->group(function () {
    Route::get('/', [InventorySerialController::class, 'index'])->name('index');
    Route::get('/create', [InventorySerialController::class, 'create'])->name('create');
    Route::post('/', [InventorySerialController::class, 'store'])->name('store');
    Route::get('/{inventorySerial}', [InventorySerialController::class, 'show'])->name('show');
    Route::get('/{inventorySerial}/edit', [InventorySerialController::class, 'edit'])->name('edit');
    Route::put('/{inventorySerial}', [InventorySerialController::class, 'update'])->name('update');
    // NOTE: mark-damaged and mark-missing routes are intentionally absent.
    // Status changes are handled by the inventory-movement module (type=adjustment).
});
```

**Why not `Route::resource`?**
The standard resource helper generates routes for `destroy` which this module does not expose.
Explicit routes are clearer, avoid unintended routes, and match the pattern used for `customers`
in this project.

---

## Named Routes Summary

| Name | Method | URL | Controller Action |
|------|--------|-----|-------------------|
| `inventory-serials.index` | GET | `/admin/inventory-serials` | `index` |
| `inventory-serials.create` | GET | `/admin/inventory-serials/create` | `create` |
| `inventory-serials.store` | POST | `/admin/inventory-serials` | `store` |
| `inventory-serials.show` | GET | `/admin/inventory-serials/{inventorySerial}` | `show` |
| `inventory-serials.edit` | GET | `/admin/inventory-serials/{inventorySerial}/edit` | `edit` |
| `inventory-serials.update` | PUT | `/admin/inventory-serials/{inventorySerial}` | `update` |

All routes inherit the admin middleware stack: `auth`, `load_perms`, `verified`, `active`.

---

## AppServiceProvider — Policy Registration

In `app/Providers/AppServiceProvider.php`, register the policy. The project registers policies
in the `$policies` array (standard Laravel Gate auto-discovery), or via an explicit `Gate::policy()` call:

```php
use App\Models\InventorySerial;
use App\Policies\InventorySerialPolicy;

// In boot() or via $policies array:
Gate::policy(InventorySerial::class, InventorySerialPolicy::class);
```

Check the existing `AppServiceProvider` to see which pattern is in use and follow it.
