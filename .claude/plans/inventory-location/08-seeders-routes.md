# InventoryLocation Module — Seeders & Routes

---

## 1. Permission Seeder

**File:** `database/seeders/InventoryLocationPermissionSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class InventoryLocationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::INVENTORY_LOCATIONS_VIEW_ANY,
            Permission::INVENTORY_LOCATIONS_VIEW,
            Permission::INVENTORY_LOCATIONS_CREATE,
            Permission::INVENTORY_LOCATIONS_EDIT,
            Permission::INVENTORY_LOCATIONS_DELETE,
            Permission::INVENTORY_LOCATIONS_RESTORE,
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        // super-admin: null-safe, future role not yet seeded
        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);

        // admin: full access
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);

        // manager: same as admin
        Role::where('name', 'manager')->first()?->givePermissionTo($permissions);

        // sales: view only
        Role::where('name', 'sales')->first()?->givePermissionTo([
            Permission::INVENTORY_LOCATIONS_VIEW_ANY,
            Permission::INVENTORY_LOCATIONS_VIEW,
        ]);
    }
}
```

---

## 2. Data Seeder

**File:** `database/seeders/InventoryLocationSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InventoryLocation;
use Illuminate\Database\Seeder;

class InventoryLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'code'        => 'L1',
                'name'        => 'Shelf L1 — Row A',
                'description' => 'First shelf, ground level, row A.',
                'is_active'   => true,
            ],
            [
                'code'        => 'L2',
                'name'        => 'Shelf L2 — Row A',
                'description' => 'Second shelf, ground level, row A.',
                'is_active'   => true,
            ],
            [
                'code'        => 'L45',
                'name'        => 'Shelf L45 — Row C',
                'description' => 'Mid warehouse, row C.',
                'is_active'   => true,
            ],
            [
                'code'        => 'L99',
                'name'        => 'Shelf L99 — Row F',
                'description' => 'Far end of warehouse, top shelf.',
                'is_active'   => true,
            ],
            [
                'code'        => 'ZONE-A',
                'name'        => 'Zone A — Staging Area',
                'description' => 'Temporary staging area for incoming stock.',
                'is_active'   => true,
            ],
            [
                'code'        => 'ZONE-B',
                'name'        => 'Zone B — Returns Bay',
                'description' => 'Receiving area for customer returns.',
                'is_active'   => true,
            ],
        ];

        foreach ($locations as $data) {
            InventoryLocation::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
```

---

## 3. DatabaseSeeder — Add Calls

In `database/seeders/DatabaseSeeder.php`, add both seeders **after** the role seeder
and any existing permission seeders:

```php
// Add to DatabaseSeeder.php after AuditLogPermissionSeeder::class:
InventoryLocationPermissionSeeder::class,
InventoryLocationSeeder::class,
// inventory-serial, inventory-movement, inventory seeders follow in their own modules
```

---

## 4. Routes

Add inside the existing admin middleware group in `routes/web.php`,
after the `product-listings` routes:

```php
use App\Http\Controllers\InventoryLocationController;

// Inventory Locations
Route::prefix('inventory-locations')->name('inventory-locations.')->group(function () {
    Route::get('/',                                [InventoryLocationController::class, 'index'])->name('index');
    Route::get('/create',                          [InventoryLocationController::class, 'create'])->name('create');
    Route::post('/',                               [InventoryLocationController::class, 'store'])->name('store');
    Route::get('/{inventoryLocation}',             [InventoryLocationController::class, 'show'])->name('show');
    Route::get('/{inventoryLocation}/edit',        [InventoryLocationController::class, 'edit'])->name('edit');
    Route::put('/{inventoryLocation}',             [InventoryLocationController::class, 'update'])->name('update');
    Route::delete('/{inventoryLocation}',          [InventoryLocationController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/restore',                   [InventoryLocationController::class, 'restore'])->name('restore');
});
```

### Named Routes Summary

| Name | Method | URL |
|------|--------|-----|
| `inventory-locations.index` | GET | `/admin/inventory-locations` |
| `inventory-locations.create` | GET | `/admin/inventory-locations/create` |
| `inventory-locations.store` | POST | `/admin/inventory-locations` |
| `inventory-locations.show` | GET | `/admin/inventory-locations/{inventoryLocation}` |
| `inventory-locations.edit` | GET | `/admin/inventory-locations/{inventoryLocation}/edit` |
| `inventory-locations.update` | PUT | `/admin/inventory-locations/{inventoryLocation}` |
| `inventory-locations.destroy` | DELETE | `/admin/inventory-locations/{inventoryLocation}` |
| `inventory-locations.restore` | POST | `/admin/inventory-locations/{id}/restore` |

### Notes on Route Design

- `restore` takes a plain `{id}` integer, not `{inventoryLocation}`, because standard
  Laravel route model binding uses `whereNull('deleted_at')` by default and would 404
  on a soft-deleted record. The controller resolves it with `withTrashed()->findOrFail($id)`.
- All 8 routes sit inside the existing `Route::middleware(['auth', 'load_perms', 'verified', 'active'])`
  group — do NOT add a separate middleware wrapper.
- Route model binding parameter name is `inventoryLocation` (camelCase of the model name).

---

## 5. Running the Seeders

```bash
# Run both seeders individually (safe to run multiple times — uses firstOrCreate)
php artisan db:seed --class=InventoryLocationPermissionSeeder
php artisan db:seed --class=InventoryLocationSeeder

# Or run from DatabaseSeeder
php artisan db:seed
```

---

## 6. Verify Installation

```bash
# Check routes are registered
php artisan route:list --name=inventory-locations

# Check permissions exist in DB
php artisan tinker
>>> \Spatie\Permission\Models\Permission::where('name', 'like', 'inventory-locations%')->pluck('name')
```
