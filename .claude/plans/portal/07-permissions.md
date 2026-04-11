# Customer Portal — Permissions & Role

The portal uses the `customer` Spatie role.
No granular permissions needed — all portal routes are behind `role:customer` middleware.

---

## Role

| Role | Who has it | What it grants |
|------|-----------|----------------|
| `customer` | Any self-registered customer | Access to `/portal/*` routes only |

---

## Middleware on Portal Routes

```php
Route::middleware(['auth', 'role:customer'])->prefix('portal')->name('portal.')-> ...
```

- `auth` — must be logged in
- `role:customer` — must have the `customer` role (Spatie)
- Admins and Staff do NOT have the `customer` role → they get 403 on portal routes
- Customers do NOT have admin roles → they get 403 on admin routes

---

## Seeder

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

        // Create the customer role if it doesn't exist
        Role::firstOrCreate([
            'name'       => 'customer',
            'guard_name' => 'web',
        ]);
    }
}
```

---

## Registering the Seeder

Add to `database/seeders/DatabaseSeeder.php`:

```php
$this->call([
    // ... existing seeders ...
    CustomerRoleSeeder::class,
]);
```

Run:
```bash
php artisan db:seed --class=CustomerRoleSeeder
```

---

## Notes
- The `customer` role has NO permissions assigned — access is controlled by middleware only
- `assignRole('customer')` is called in `CustomerService::register()` automatically
- Admins creating customers via the admin panel do NOT auto-create a portal account — portal account is only created via self-registration
- If a customer is blocked (`status = blocked`), they can still log in — status only affects admin-side display. If you want to block login, add a check in `RegisterController::login()` against `$customer->status`
