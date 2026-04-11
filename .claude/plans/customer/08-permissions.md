# Customer Module — Permissions

## Permission Constants

Add to `app/Enums/Permission.php`:

```php
// ── Customers ──────────────────────────────────────────────────────────────
const CUSTOMERS_VIEW_ANY = 'customers.view-any';
const CUSTOMERS_VIEW     = 'customers.view';
const CUSTOMERS_CREATE   = 'customers.create';
const CUSTOMERS_EDIT     = 'customers.edit';
const CUSTOMERS_DELETE   = 'customers.delete';
const CUSTOMERS_RESTORE  = 'customers.restore';
const CUSTOMERS_ASSIGN   = 'customers.assign';
// customers.import — added later with ImportExport module
```

## Permission Matrix

| Permission            | admin | manager | sales |
|-----------------------|:-----:|:-------:|:-----:|
| customers.view-any    |   ✓   |    ✓    |   ✓   |
| customers.view        |   ✓   |    ✓    |   ✓   |
| customers.create      |   ✓   |    ✓    |       |
| customers.edit        |   ✓   |    ✓    |   ✓   |
| customers.delete      |   ✓   |         |       |
| customers.restore     |   ✓   |         |       |
| customers.assign      |   ✓   |    ✓    |       |

> **Sales + `customers.edit`**: Sales has this permission but `CustomerPolicy::update()`
> restricts them to assigned customers only, and `CustomerPolicy::changeStatus()` blocks
> them from changing status (even on assigned customers).

## RoleSeeder Updates

In `database/seeders/RoleSeeder.php`, add customer permissions to each role's
`syncPermissions()` call:

```php
// Admin — all customer permissions
$admin->syncPermissions([
    // ... existing user/dept/role permissions ...
    Permission::CUSTOMERS_VIEW_ANY,
    Permission::CUSTOMERS_VIEW,
    Permission::CUSTOMERS_CREATE,
    Permission::CUSTOMERS_EDIT,
    Permission::CUSTOMERS_DELETE,
    Permission::CUSTOMERS_RESTORE,
    Permission::CUSTOMERS_ASSIGN,
]);

// Manager — no delete/restore
$manager->syncPermissions([
    // ... existing user/dept permissions ...
    Permission::CUSTOMERS_VIEW_ANY,
    Permission::CUSTOMERS_VIEW,
    Permission::CUSTOMERS_CREATE,
    Permission::CUSTOMERS_EDIT,
    Permission::CUSTOMERS_ASSIGN,
]);

// Sales — view + edit only (policy restricts to assigned; no status change)
$sales->syncPermissions([
    // ... existing user permissions ...
    Permission::CUSTOMERS_VIEW_ANY,
    Permission::CUSTOMERS_VIEW,
    Permission::CUSTOMERS_EDIT,
]);
```

## Policy → Permission + Scope Mapping

| Policy method     | Permission constant   | Role scope check                      |
|-------------------|-----------------------|---------------------------------------|
| `viewAny()`       | CUSTOMERS_VIEW_ANY    | None — all roles see the route        |
| `view()`          | CUSTOMERS_VIEW        | manager: same dept; sales: assigned   |
| `create()`        | CUSTOMERS_CREATE      | None                                  |
| `update()`        | CUSTOMERS_EDIT        | manager: same dept; sales: assigned   |
| `changeStatus()`  | CUSTOMERS_EDIT        | admin or manager only (not sales)     |
| `assign()`        | CUSTOMERS_ASSIGN      | None                                  |
| `delete()`        | CUSTOMERS_DELETE      | None (admin only by matrix)           |
| `restore()`       | CUSTOMERS_RESTORE     | None (admin only by matrix)           |

## AppServiceProvider

See `02-model.md` for the full `boot()` block. The three additions for the Customer module are:

```php
// New imports:
use App\Models\Customer;
use App\Observers\CustomerObserver;
use App\Policies\CustomerPolicy;

// Inside boot():
Gate::policy(Customer::class, CustomerPolicy::class);
Customer::observe(CustomerObserver::class);
Route::bind('trashedCustomer', fn ($id) => Customer::onlyTrashed()->findOrFail($id));
```

All three lines must be added together — the Route::bind is required for the restore
route's `{trashedCustomer}` parameter to resolve soft-deleted records correctly.

## Gate::before() (no changes)

The existing superadmin `Gate::before()` bypass automatically covers all customer
permissions for roles where `is_super = true`. Nothing to change.

## Middleware (no changes)

Customer routes use the same shared middleware stack:
```
auth → load_perms → verified → active
```

No `admin` middleware on customer routes — access is policy-driven,
not middleware-driven (same as User and Department modules).
