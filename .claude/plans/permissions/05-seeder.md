# Permissions Module — Permission Enum + RoleSeeder Updates

## `app/Enums/Permission.php` — Add Roles Permissions

Add two new constants:

```php
// Roles
const ROLES_VIEW   = 'roles.view';
const ROLES_MANAGE = 'roles.manage';
```

## `database/seeders/RoleSeeder.php` — Full Update

- Seed `roles.view` and `roles.manage` permissions
- Set `is_admin` / `is_super` flags on roles after creation
- Clear role caches after seeding

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission as SpatiePermission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Seed all permissions
        $permissions = [
            Permission::USERS_VIEW_ANY,
            Permission::USERS_VIEW,
            Permission::USERS_CREATE,
            Permission::USERS_EDIT,
            Permission::USERS_DELETE,
            Permission::USERS_RESTORE,
            Permission::USERS_CHANGE_STATUS,
            Permission::USERS_RESET_PASSWORD,
            Permission::DEPARTMENTS_VIEW_ANY,
            Permission::DEPARTMENTS_VIEW,
            Permission::DEPARTMENTS_CREATE,
            Permission::DEPARTMENTS_EDIT,
            Permission::DEPARTMENTS_DELETE,
            Permission::DEPARTMENTS_RESTORE,
            Permission::ROLES_VIEW,
            Permission::ROLES_MANAGE,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Admin — is_admin=true, all permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->update(['is_admin' => true, 'is_super' => false]);
        $admin->syncPermissions([
            Permission::USERS_VIEW_ANY, Permission::USERS_VIEW,
            Permission::USERS_CREATE,   Permission::USERS_EDIT,
            Permission::USERS_DELETE,   Permission::USERS_RESTORE,
            Permission::USERS_CHANGE_STATUS, Permission::USERS_RESET_PASSWORD,
            Permission::DEPARTMENTS_VIEW_ANY, Permission::DEPARTMENTS_VIEW,
            Permission::DEPARTMENTS_CREATE,   Permission::DEPARTMENTS_EDIT,
            Permission::DEPARTMENTS_DELETE,   Permission::DEPARTMENTS_RESTORE,
            Permission::ROLES_VIEW,     Permission::ROLES_MANAGE,
        ]);

        // 3. Manager — is_admin=false
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->update(['is_admin' => false, 'is_super' => false]);
        $manager->syncPermissions([
            Permission::USERS_VIEW_ANY, Permission::USERS_VIEW, Permission::USERS_EDIT,
            Permission::DEPARTMENTS_VIEW_ANY, Permission::DEPARTMENTS_VIEW,
        ]);

        // 4. Sales — is_admin=false
        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $sales->update(['is_admin' => false, 'is_super' => false]);
        $sales->syncPermissions([
            Permission::USERS_VIEW,
            Permission::USERS_EDIT,
        ]);

        // 5. Clear middleware caches
        Cache::forget('roles.admin');
        Cache::forget('roles.super');
    }
}
```
