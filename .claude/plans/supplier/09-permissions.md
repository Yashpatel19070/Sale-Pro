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
| `suppliers.changeStatus` | ✅ | ✅ | ✅ | ❌ |

---

## Full Seeder Code

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SupplierPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'suppliers.viewAny',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.delete',
            'suppliers.changeStatus',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $admin      = Role::firstOrCreate(['name' => 'admin']);
        $manager    = Role::firstOrCreate(['name' => 'manager']);
        $sales      = Role::firstOrCreate(['name' => 'sales']);

        $superAdmin->givePermissionTo($permissions);
        $admin->givePermissionTo($permissions);

        $manager->givePermissionTo([
            'suppliers.viewAny',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.changeStatus',
        ]);

        $sales->givePermissionTo([
            'suppliers.viewAny',
            'suppliers.view',
        ]);
    }
}
```

---

## Update app/Enums/Permission.php

Add supplier constants to `app/Enums/Permission.php` alongside other module constants:

```php
// Suppliers
const SUPPLIERS_VIEW_ANY      = 'suppliers.viewAny';
const SUPPLIERS_VIEW          = 'suppliers.view';
const SUPPLIERS_CREATE        = 'suppliers.create';
const SUPPLIERS_UPDATE        = 'suppliers.update';
const SUPPLIERS_DELETE        = 'suppliers.delete';
const SUPPLIERS_CHANGE_STATUS = 'suppliers.changeStatus';
```

These string values MUST match exactly what `SupplierPermissionSeeder` seeds — both use camelCase (`viewAny`, `changeStatus`) consistent with the customer module pattern.

---

## Register in DatabaseSeeder

Add to `database/seeders/DatabaseSeeder.php`:

```php
$this->call([
    // ... existing seeders ...
    SupplierPermissionSeeder::class,
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
- `firstOrCreate` is safe to run multiple times — no duplicate permissions
- Super Admin typically has a Gate bypass via `Gate::before()` — seeder still assigns permissions for completeness
- Sales role maps to the role used in forbidden tests (see project memory: use 'sales' for forbidden tests)
- Manager gets all except `suppliers.delete` — managers can create and edit but cannot remove suppliers
