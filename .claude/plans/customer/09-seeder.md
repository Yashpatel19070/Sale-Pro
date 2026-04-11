# Customer Module — Seeder

## File: `database/seeders/CustomerSeeder.php`

Seeds 5 demo customers for local development. Depends on RoleSeeder and DepartmentSeeder
having already run (users + departments must exist).

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $sales   = User::role('sales')->first();
        $manager = User::role('manager')->first();
        $dept    = Department::first();

        // Named customer — idempotent via firstOrCreate (safe to seed multiple times)
        Customer::firstOrCreate(
            ['email' => 'alice.chen@example.com'],
            [
                'first_name'    => 'Alice',
                'last_name'     => 'Chen',
                'company_name'  => 'Acme Corp',
                'status'        => CustomerStatus::Active,
                'source'        => CustomerSource::Referral,
                'assigned_to'   => $sales?->id,
                'department_id' => $dept?->id,
                'city'          => 'Sydney',
                'state'         => 'NSW',
                'country'       => 'Australia',
            ]
        );

        // Four random customers — only created on first seed (check count to avoid dupes)
        if (Customer::count() < 5) {
            Customer::factory()->count(4)->create([
                'assigned_to'   => $sales?->id,
                'department_id' => $dept?->id,
            ]);
        }
    }
}
```

## `DatabaseSeeder.php` Update

Add `CustomerSeeder` after `DepartmentSeeder` in the `$this->call([...])` array.
Order matters — departments and users must exist before customers are seeded.

```php
$this->call([
    RoleSeeder::class,
    DepartmentSeeder::class,
    CustomerSeeder::class,   // ← add here
]);
```

## Notes
- Named customer uses `firstOrCreate` — safe to run `db:seed` multiple times.
- Random 4 customers are skipped if count >= 5 — avoids duplication on repeated seeds.
- For clean state: `php artisan migrate:fresh --seed`.
- CustomerSeeder is **not** called in tests — tests use `Customer::factory()` directly.
