# Audit Log Module — Seeders & Routes

## Permission Seeder
`database/seeders/AuditLogPermissionSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AuditLogPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::AUDIT_LOG_VIEW_ANY,
            Permission::AUDIT_LOG_VIEW,
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // Super Admin — all permissions
        $superAdmin = Role::findByName('super-admin');
        $superAdmin->givePermissionTo($permissions);

        // Admin — all permissions
        $admin = Role::findByName('admin');
        $admin->givePermissionTo($permissions);

        // Staff — no access by default
        // Can be granted via role management if required
    }
}
```

---

## DatabaseSeeder
Add after existing seeders:

```php
$this->call([
    // ... existing ...
    AuditLogPermissionSeeder::class,
]);
```

> No data seeder needed — activity log is populated by usage, not seeded.

---

## Routes
Add inside the **admin middleware group** in `routes/web.php`:

```php
// Audit Log (read-only)
Route::get('audit-log',           [AuditLogController::class, 'index'])->name('audit-log.index');
Route::get('audit-log/{activity}', [AuditLogController::class, 'show'])->name('audit-log.show');
```

Add import at top of routes file:
```php
use App\Http\Controllers\AuditLogController;
```

---

## AppServiceProvider — full additions summary

All additions to `app/Providers/AppServiceProvider.php`:

```php
// Imports to add:
use App\Listeners\LogAuthActivity;
use App\Policies\AuditLogPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

// Inside boot():
Gate::policy(Activity::class, AuditLogPolicy::class);

Event::listen(Login::class,  [LogAuthActivity::class, 'handleLogin']);
Event::listen(Logout::class, [LogAuthActivity::class, 'handleLogout']);
Event::listen(Failed::class, [LogAuthActivity::class, 'handleFailed']);
```

---

## Checklist

- [ ] `AuditLogPermissionSeeder` — 2 permissions; super-admin + admin only; staff no access
- [ ] `DatabaseSeeder` calls `AuditLogPermissionSeeder`
- [ ] 2 read-only routes (`index`, `show`) inside admin middleware group
- [ ] No POST/PUT/DELETE/PATCH routes — audit log is append-only
- [ ] `AuditLogController` imported in routes file
- [ ] Policy and listeners registered in `AppServiceProvider`
- [ ] `php artisan db:seed --class=AuditLogPermissionSeeder` runs clean
