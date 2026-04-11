# Portal Foundation — Seeder

One seeder: create the `customer` role.

---

## CustomerRoleSeeder

**File:** `database/seeders/CustomerRoleSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CustomerRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate([
            'name'       => 'customer',
            'guard_name' => 'web',
        ]);
    }
}
```

---

## Register in DatabaseSeeder

**File:** `database/seeders/DatabaseSeeder.php` — add:

```php
$this->call([
    // ... existing seeders ...
    CustomerRoleSeeder::class,
]);
```

---

## Run

```bash
php artisan db:seed --class=CustomerRoleSeeder
```

---

## Notes
- `firstOrCreate` — safe to run multiple times, no duplicates
- `customer` role has NO permissions — access is controlled by `role:customer` middleware only
- Role is automatically assigned in `CustomerService::register()` via `$user->assignRole('customer')`
