# Supplier Module — Permissions Seeder

**File:** `database/seeders/SupplierPermissionSeeder.php`

---

## Permission Matrix

| Permission | Super Admin | Admin | Manager | Sales |
|------------|-------------|-------|---------|-------|
| `suppliers.viewAny` | ✅ | ✅ | ✅ | ✅ |
| `suppliers.view` | ✅ | ✅ | ✅ | ✅ |
| `suppliers.create` | ✅ | ✅ | ✅ | ❌ |
| `suppliers.update` | ✅ | ✅ | ✅ | ❌ |
| `suppliers.delete` | ✅ | ✅ | ❌ | ❌ |
| `suppliers.restore` | ✅ | ✅ | ❌ | ❌ |
| `suppliers.changeStatus` | ✅ | ✅ | ✅ | ❌ |

---

## Full Seeder Code

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SupplierPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::SUPPLIERS_VIEW_ANY,
            Permission::SUPPLIERS_VIEW,
            Permission::SUPPLIERS_CREATE,
            Permission::SUPPLIERS_UPDATE,
            Permission::SUPPLIERS_DELETE,
            Permission::SUPPLIERS_RESTORE,
            Permission::SUPPLIERS_CHANGE_STATUS,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($permissions);

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->givePermissionTo([
            Permission::SUPPLIERS_VIEW_ANY,
            Permission::SUPPLIERS_VIEW,
            Permission::SUPPLIERS_CREATE,
            Permission::SUPPLIERS_UPDATE,
            Permission::SUPPLIERS_CHANGE_STATUS,
        ]);

        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $sales->givePermissionTo([
            Permission::SUPPLIERS_VIEW_ANY,
            Permission::SUPPLIERS_VIEW,
        ]);
    }
}
```

---

## Permission.php Constants

These constants are in `app/Enums/Permission.php`:

```php
// Suppliers
const SUPPLIERS_VIEW_ANY      = 'suppliers.viewAny';
const SUPPLIERS_VIEW          = 'suppliers.view';
const SUPPLIERS_CREATE        = 'suppliers.create';
const SUPPLIERS_UPDATE        = 'suppliers.update';
const SUPPLIERS_DELETE        = 'suppliers.delete';
const SUPPLIERS_RESTORE       = 'suppliers.restore';
const SUPPLIERS_CHANGE_STATUS = 'suppliers.changeStatus';
```

String values match exactly what the seeder seeds — camelCase (`viewAny`, `changeStatus`) consistent with the customer module pattern.

---

## Register in DatabaseSeeder

Add to `database/seeders/DatabaseSeeder.php`:

```php
$this->call([
    // ... existing seeders ...
    SupplierPermissionSeeder::class,
    SupplierSeeder::class,
]);
```

---

## Running the Seeder

```bash
php artisan db:seed --class=SupplierPermissionSeeder
```

Or as part of full seed:
```bash
php artisan db:seed
```

---

## Notes
- Use `Permission::SUPPLIERS_*` constants throughout — never raw strings
- `super-admin` role uses null-safe `where()->first()?->` pattern (role may not exist in all environments)
- `admin`, `manager`, `sales` use `firstOrCreate` — safe to run multiple times
- `SpatiePermission::firstOrCreate` — no duplicate permissions created on re-run
- Sales role maps to the role used in forbidden tests (see project memory: use 'sales' for forbidden tests)
- Manager gets all except `suppliers.delete` and `suppliers.restore` — can create/edit but cannot remove or restore suppliers
