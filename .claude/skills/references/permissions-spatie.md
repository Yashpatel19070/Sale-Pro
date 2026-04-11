# Permissions Reference (Spatie Laravel Permission)

## Setup

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\LaravelPermission\PermissionServiceProvider"
php artisan migrate
```

Add `HasRoles` trait to `User` model:
```php
use Spatie\LaravelPermission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

---

## Permission Model — Simple & Flat

**Rule: `posts.edit` = can edit ALL posts. No ownership checks.**

Permissions are flat resource actions. Roles are named bundles of permissions.

```
{resource}.view      — read / list / show
{resource}.create    — create new records
{resource}.edit      — update any record
{resource}.delete    — delete any record
```

---

## Permission Constants — Never Raw Strings

```php
// app/Enums/Permission.php
<?php

namespace App\Enums;

class Permission
{
    const USERS_VIEW    = 'users.view';
    const USERS_CREATE  = 'users.create';
    const USERS_EDIT    = 'users.edit';
    const USERS_DELETE  = 'users.delete';

    const ROLES_VIEW    = 'roles.view';
    const ROLES_MANAGE  = 'roles.manage';

    const POSTS_VIEW    = 'posts.view';
    const POSTS_CREATE  = 'posts.create';
    const POSTS_EDIT    = 'posts.edit';
    const POSTS_DELETE  = 'posts.delete';

    const SETTINGS_VIEW = 'settings.view';
    const SETTINGS_EDIT = 'settings.edit';

    const SYSTEM_MANAGE = 'system.manage';
}
```

Usage — constants everywhere, raw strings nowhere:
```php
// FormRequest
return $this->user()->can(Permission::POSTS_EDIT);

// Controller
Gate::authorize(Permission::POSTS_EDIT);
abort_if($user->cannot(Permission::USERS_DELETE), 403);

// Route
->middleware('permission:' . Permission::POSTS_EDIT)

// Blade — string value only (Blade can't import PHP classes)
@can('posts.edit') ... @endcan
```

---

## Role Definitions

| Role | Access | `is_admin` | `is_super` |
|------|--------|:----------:|:----------:|
| `superadmin` | Bypasses all checks via Gate | ✅ | ✅ |
| `admin` | Full operational control | ✅ | ❌ |
| `admin_rw` | Read + write, no delete | ✅ | ❌ |
| `admin_readonly` | Read-only admin panel | ✅ | ❌ |
| `editor` | Content management only | ❌ | ❌ |
| `viewer` | Read-only — **default on registration** | ❌ | ❌ |

---

## Permission Matrix

| Permission | superadmin | admin | admin_rw | admin_readonly | editor | viewer |
|------------|:----------:|:-----:|:--------:|:--------------:|:------:|:------:|
| `users.view` | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| `users.create` | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.edit` | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.delete` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `roles.view` | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| `roles.manage` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `posts.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `posts.create` | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| `posts.edit` | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| `posts.delete` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| `settings.view` | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| `settings.edit` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `system.manage` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## Seeder

```php
<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\LaravelPermission\Models\Permission as SpatiePermission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\LaravelPermission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Permissions
        foreach ([
            Permission::USERS_VIEW,    Permission::USERS_CREATE,
            Permission::USERS_EDIT,    Permission::USERS_DELETE,
            Permission::ROLES_VIEW,    Permission::ROLES_MANAGE,
            Permission::POSTS_VIEW,    Permission::POSTS_CREATE,
            Permission::POSTS_EDIT,    Permission::POSTS_DELETE,
            Permission::SETTINGS_VIEW, Permission::SETTINGS_EDIT,
            Permission::SYSTEM_MANAGE,
        ] as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Roles — flags drive middleware, not hardcoded names
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'])
            ->update(['is_admin' => true, 'is_super' => true]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])
            ->update(['is_admin' => true, 'is_super' => false]);
        Role::where('name', 'admin')->first()->syncPermissions([
            Permission::USERS_VIEW,    Permission::USERS_CREATE,
            Permission::USERS_EDIT,    Permission::USERS_DELETE,
            Permission::ROLES_VIEW,
            Permission::POSTS_VIEW,    Permission::POSTS_CREATE,
            Permission::POSTS_EDIT,    Permission::POSTS_DELETE,
            Permission::SETTINGS_VIEW, Permission::SETTINGS_EDIT,
        ]);

        Role::firstOrCreate(['name' => 'admin_rw', 'guard_name' => 'web'])
            ->update(['is_admin' => true, 'is_super' => false]);
        Role::where('name', 'admin_rw')->first()->syncPermissions([
            Permission::USERS_VIEW,  Permission::USERS_CREATE, Permission::USERS_EDIT,
            Permission::POSTS_VIEW,  Permission::POSTS_CREATE, Permission::POSTS_EDIT,
            Permission::SETTINGS_VIEW,
        ]);

        Role::firstOrCreate(['name' => 'admin_readonly', 'guard_name' => 'web'])
            ->update(['is_admin' => true, 'is_super' => false]);
        Role::where('name', 'admin_readonly')->first()->syncPermissions([
            Permission::USERS_VIEW, Permission::ROLES_VIEW,
            Permission::POSTS_VIEW, Permission::SETTINGS_VIEW,
        ]);

        Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web'])
            ->update(['is_admin' => false, 'is_super' => false]);
        Role::where('name', 'editor')->first()->syncPermissions([
            Permission::POSTS_VIEW, Permission::POSTS_CREATE,
            Permission::POSTS_EDIT, Permission::POSTS_DELETE,
        ]);

        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web'])
            ->update(['is_admin' => false, 'is_super' => false]);
        Role::where('name', 'viewer')->first()->syncPermissions([Permission::POSTS_VIEW]);

        // 3. Clear flag caches
        Cache::forget('roles.admin');
        Cache::forget('roles.super');
    }
}
```

---

## Superadmin Gate Bypass

DB-driven — reads `is_super` flag, cached, resolved once per request:

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    $superRoles = null;

    Gate::before(function ($user, $ability) use (&$superRoles) {
        if ($superRoles === null) {
            $superRoles = Cache::remember('roles.super', now()->addHours(6), function () {
                return Role::where('is_super', true)->pluck('name');
            });
        }

        if ($user->hasAnyRole($superRoles)) {
            return true;
        }
    });
}
```

---

## Checking Permissions

```php
// ✅ Always check permission — not role
$user->can(Permission::POSTS_EDIT);
$user->cannot(Permission::USERS_DELETE);
Gate::authorize(Permission::POSTS_EDIT);
abort_if($user->cannot(Permission::POSTS_DELETE), 403);

// ⚠️ Role check — UI labels only, never for access control
$user->hasRole('superadmin');
$user->hasAnyRole(['admin', 'admin_rw']);

// Listing
$user->getRoleNames();
$user->getPermissionNames();
$user->hasPermissionTo(Permission::POSTS_EDIT);
$user->hasAnyPermission([Permission::POSTS_EDIT, Permission::POSTS_DELETE]);
$user->hasAllPermissions([Permission::POSTS_VIEW, Permission::POSTS_EDIT]);
```

---

## Blade — Permission Directives

```blade
{{-- ✅ Permission-based — controls access --}}
@can('posts.create')
    <a href="{{ route('posts.create') }}">New Post</a>
@endcan

@cannot('settings.edit')
    <p class="text-sm text-gray-400">Read-only access</p>
@endcannot

{{-- ⚠️ Role-based — UI labels only --}}
@role('superadmin')
    <span class="badge">Super Admin</span>
@endrole
```

---

## Assigning & Revoking

```php
$user->assignRole('editor');
$user->syncRoles(['viewer']);
$user->removeRole('editor');
$user->givePermissionTo(Permission::POSTS_DELETE);  // direct permission
$user->revokePermissionTo(Permission::POSTS_DELETE);
```

---

## 403 Handling

```php
// Controller
abort_if($user->cannot(Permission::POSTS_DELETE), 403);
abort_unless($user->can(Permission::SETTINGS_EDIT), 403, 'Access denied.');
Gate::authorize(Permission::POSTS_EDIT); // throws 403 automatically
```

---

## Deployment Checklist

```bash
php artisan permission:cache-reset
php artisan cache:forget roles.admin
php artisan cache:forget roles.super
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
