# Roles — Setup (Run Before All Module Seeders)

## Purpose
Creates all application roles. Must run before any permission seeder — all seeders
call `Role::findByName()` which throws if the role doesn't exist.

## All Roles

| Role | Who |
|------|-----|
| `super-admin` | Full access to everything |
| `admin` | Full access except role management |
| `manager` | Operational management — approve/reopen/cancel |
| `sales` | Customer-facing operations |
| `procurement` | Purchase Order creation and receiving |
| `warehouse` | Physical handling — visual, serial assign, shelf |
| `tech` | Technical inspection |
| `qa` | Quality assurance |

## RoleSeeder

```php
<?php
// database/seeders/RoleSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super-admin',
            'admin',
            'manager',
            'sales',
            'procurement',
            'warehouse',
            'tech',
            'qa',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
```

## DatabaseSeeder Call Order

```php
// database/seeders/DatabaseSeeder.php

$this->call([
    RoleSeeder::class,                    // ← MUST be first

    // Existing module seeders
    // ...

    // New module seeders (all depend on roles existing)
    SupplierPermissionSeeder::class,
    PurchaseOrderPermissionSeeder::class,
    PipelinePermissionSeeder::class,
]);
```

## Notes

- `firstOrCreate` is idempotent — safe to run multiple times.
- `guard_name = 'web'` matches the project's Spatie guard configuration.
- `super-admin` role should bypass all policy checks via a `Gate::before()` rule if not already set.
  Check `app/Providers/AppServiceProvider.php` — add if missing:
  ```php
  Gate::before(fn (User $user) => $user->hasRole('super-admin') ? true : null);
  ```
