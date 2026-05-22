# Customer Address Module — Permissions & Seeder

---

## Step 1 — Add Constants to `app/Enums/Permission.php`

Append the following block to `Permission.php` after the existing constants:

```php
// Customer Addresses
const CUSTOMER_ADDRESSES_VIEW_ANY   = 'customer-addresses.view-any';
const CUSTOMER_ADDRESSES_VIEW       = 'customer-addresses.view';
const CUSTOMER_ADDRESSES_CREATE     = 'customer-addresses.create';
const CUSTOMER_ADDRESSES_UPDATE     = 'customer-addresses.update';
const CUSTOMER_ADDRESSES_DELETE     = 'customer-addresses.delete';
const CUSTOMER_ADDRESSES_SET_DEFAULT = 'customer-addresses.set-default';
```

---

## Step 2 — Create the Seeder

**File:** `database/seeders/CustomerAddressPermissionSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;

class CustomerAddressPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::CUSTOMER_ADDRESSES_VIEW_ANY,
            Permission::CUSTOMER_ADDRESSES_VIEW,
            Permission::CUSTOMER_ADDRESSES_CREATE,
            Permission::CUSTOMER_ADDRESSES_UPDATE,
            Permission::CUSTOMER_ADDRESSES_DELETE,
            Permission::CUSTOMER_ADDRESSES_SET_DEFAULT,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        $admin   = Role::findByName('admin', 'web');
        $manager = Role::findByName('manager', 'web');
        $sales   = Role::findByName('sales', 'web');

        // admin — full access
        $admin->givePermissionTo($permissions);

        // manager — no delete
        $manager->givePermissionTo([
            Permission::CUSTOMER_ADDRESSES_VIEW_ANY,
            Permission::CUSTOMER_ADDRESSES_VIEW,
            Permission::CUSTOMER_ADDRESSES_CREATE,
            Permission::CUSTOMER_ADDRESSES_UPDATE,
            Permission::CUSTOMER_ADDRESSES_SET_DEFAULT,
        ]);

        // sales — view only
        $sales->givePermissionTo([
            Permission::CUSTOMER_ADDRESSES_VIEW_ANY,
            Permission::CUSTOMER_ADDRESSES_VIEW,
        ]);
    }
}
```

---

## Step 3 — Register in DatabaseSeeder

Add `CustomerAddressPermissionSeeder` to `database/seeders/DatabaseSeeder.php`:

```php
$this->call([
    // ... existing seeders ...
    CustomerAddressPermissionSeeder::class,
]);
```

---

## Step 3b — Create the Data Seeder

**File:** `database/seeders/CustomerAddressSeeder.php`

Seeds 1–3 sample addresses per customer. Marks the first as default via `forceFill`.
Must run after `CustomerSeeder`. Register in `DatabaseSeeder` immediately after `CustomerSeeder::class`.

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Seeder;

class CustomerAddressSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            'label', 'first_name', 'last_name', 'email', 'phone',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
        ];

        foreach (Customer::all() as $customer) {
            $count = fake()->numberBetween(1, 3);
            $first = null;

            for ($i = 0; $i < $count; $i++) {
                $data = CustomerAddressFactory::new()->make(['customer_id' => $customer->id])->only($fields);
                $address = $customer->addresses()->create($data);

                if ($first === null) {
                    $first = $address;
                }
            }

            $first?->forceFill(['is_default' => true])->save();
        }
    }
}
```

> **Note:** Pass `['customer_id' => $customer->id]` to `make()` — prevents the factory's
> `Customer::factory()` default from creating spurious extra customers during seeding.

---

## Step 4 — Run the Seeders

```bash
php artisan db:seed --class=CustomerAddressPermissionSeeder
```

---

## Permissions Reference

| Constant | String Value | admin | manager | sales |
|----------|-------------|:-----:|:-------:|:-----:|
| `CUSTOMER_ADDRESSES_VIEW_ANY` | `customer-addresses.view-any` | ✅ | ✅ | ✅ |
| `CUSTOMER_ADDRESSES_VIEW` | `customer-addresses.view` | ✅ | ✅ | ✅ |
| `CUSTOMER_ADDRESSES_CREATE` | `customer-addresses.create` | ✅ | ✅ | ❌ |
| `CUSTOMER_ADDRESSES_UPDATE` | `customer-addresses.update` | ✅ | ✅ | ❌ |
| `CUSTOMER_ADDRESSES_DELETE` | `customer-addresses.delete` | ✅ | ❌ | ❌ |
| `CUSTOMER_ADDRESSES_SET_DEFAULT` | `customer-addresses.set-default` | ✅ | ✅ | ❌ |

> `manager` cannot delete — protects the `shipments.customer_address_id` FK from accidental removal.

---

## Notes

- `Permission::firstOrCreate` — safe to run multiple times, no duplicates
- `givePermissionTo` adds permissions — does not remove existing ones
- Roles (`admin`, `manager`, `sales`) must exist before this seeder runs — `RoleSeeder` must run first
- `forgetCachedPermissions()` at the top prevents stale cache from blocking permission checks
- Policy uses `$user->can(Permission::CUSTOMER_ADDRESSES_*)` constants — must match these string values exactly
