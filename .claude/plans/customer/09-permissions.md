# Customer Module — Permissions & Seeder

---

## Permissions List

| Permission | Used In |
|------------|---------|
| `customers.viewAny` | CustomerPolicy::viewAny — list all customers |
| `customers.view` | CustomerPolicy::view — view a single customer |
| `customers.create` | CustomerPolicy::create — create a customer |
| `customers.update` | CustomerPolicy::update — edit a customer |
| `customers.delete` | CustomerPolicy::delete — soft-delete a customer |
| `customers.changeStatus` | CustomerPolicy::changeStatus — change customer status |

---

## Role Assignment

| Role | Permissions |
|------|-------------|
| super_admin | All 6 permissions |
| admin | All 6 permissions |
| staff | `customers.viewAny`, `customers.view` only |

---

## Seeder File

**File:** `database/seeders/CustomerPermissionSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CustomerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'customers.viewAny',
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'customers.changeStatus',
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Assign to roles — roles must already exist
        $superAdmin = Role::findByName('super_admin', 'web');
        $admin      = Role::findByName('admin', 'web');
        $staff      = Role::findByName('staff', 'web');

        $superAdmin->givePermissionTo($permissions);
        $admin->givePermissionTo($permissions);

        $staff->givePermissionTo([
            'customers.viewAny',
            'customers.view',
        ]);
    }
}
```

---

## Registering the Seeder

Add `CustomerPermissionSeeder` to `database/seeders/DatabaseSeeder.php`:

```php
$this->call([
    // ... existing seeders ...
    CustomerPermissionSeeder::class,
]);
```

---

## Running the Seeder

```bash
php artisan db:seed --class=CustomerPermissionSeeder
```

---

## Notes
- `Permission::firstOrCreate` — safe to run multiple times, no duplicates
- `givePermissionTo` on a role replaces nothing — it only adds new permissions
- Roles (`super_admin`, `admin`, `staff`) must exist before this seeder runs
- If roles don't exist yet, run the role seeder first
- `forgetCachedPermissions()` must be called at the top to avoid stale cache issues
