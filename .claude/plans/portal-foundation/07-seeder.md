# Portal Foundation — Seeder

## No Seeder Required

With the separate `customer` guard architecture, customers authenticate directly against
the `customers` table. No Spatie role is needed for customers.

**`CustomerRoleSeeder` is deleted.**

- Remove `CustomerRoleSeeder::class` from `database/seeders/DatabaseSeeder.php`
- Remove `CustomerRoleSeeder::class` from `database/seeders/E2ESeeder.php`
- Delete file: `database/seeders/CustomerRoleSeeder.php`

---

## CustomerFactory — required for tests

`database/factories/CustomerFactory.php` must include:

```php
'password'          => Hash::make('password'),
'email_verified_at' => now(),
```

So `Customer::factory()->create()` produces a verified, loginable customer without extra setup.

---

## Notes

- No `customer` Spatie role — guard enforces separation, not role
- `auth:customer` middleware on portal routes is the only gate for customers
- Admin/staff cannot access portal routes — `auth:customer` requires a Customer model, not a User
