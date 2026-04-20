<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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
            Permission::CUSTOMERS_VIEW_ANY,
            Permission::CUSTOMERS_VIEW,
            Permission::CUSTOMERS_CREATE,
            Permission::CUSTOMERS_EDIT,
            Permission::CUSTOMERS_DELETE,
            Permission::CUSTOMERS_RESTORE,
            Permission::CUSTOMERS_ASSIGN,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Admin — is_admin=true, all permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->update(['is_admin' => true, 'is_super' => false]);
        $admin->syncPermissions([
            Permission::USERS_VIEW_ANY,       Permission::USERS_VIEW,
            Permission::USERS_CREATE,         Permission::USERS_EDIT,
            Permission::USERS_DELETE,         Permission::USERS_RESTORE,
            Permission::USERS_CHANGE_STATUS,  Permission::USERS_RESET_PASSWORD,
            Permission::DEPARTMENTS_VIEW_ANY, Permission::DEPARTMENTS_VIEW,
            Permission::DEPARTMENTS_CREATE,   Permission::DEPARTMENTS_EDIT,
            Permission::DEPARTMENTS_DELETE,   Permission::DEPARTMENTS_RESTORE,
            Permission::ROLES_VIEW,           Permission::ROLES_MANAGE,
            Permission::CUSTOMERS_VIEW_ANY,   Permission::CUSTOMERS_VIEW,
            Permission::CUSTOMERS_CREATE,     Permission::CUSTOMERS_EDIT,
            Permission::CUSTOMERS_DELETE,     Permission::CUSTOMERS_RESTORE,
            Permission::CUSTOMERS_ASSIGN,
        ]);

        // 3. Manager — is_admin=false
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->update(['is_admin' => false, 'is_super' => false]);
        $manager->syncPermissions([
            Permission::USERS_VIEW_ANY,       Permission::USERS_VIEW,
            Permission::USERS_EDIT,
            Permission::DEPARTMENTS_VIEW_ANY, Permission::DEPARTMENTS_VIEW,
            Permission::CUSTOMERS_VIEW_ANY,   Permission::CUSTOMERS_VIEW,
            Permission::CUSTOMERS_CREATE,     Permission::CUSTOMERS_EDIT,
            Permission::CUSTOMERS_ASSIGN,
        ]);

        // 4. Sales — is_admin=false
        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $sales->update(['is_admin' => false, 'is_super' => false]);
        $sales->syncPermissions([
            Permission::USERS_VIEW,
            Permission::USERS_EDIT,
            Permission::CUSTOMERS_VIEW_ANY,
            Permission::CUSTOMERS_VIEW,
            Permission::CUSTOMERS_EDIT,
        ]);

        // 5. Super Admin — is_super=true, bypasses all Gate/policy checks
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->update(['is_admin' => true, 'is_super' => true]);

        // 6–9. Non-admin roles with no base permissions assigned here
        foreach (['procurement', 'warehouse', 'tech', 'qa'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'])
                ->update(['is_admin' => false, 'is_super' => false]);
        }

        // 10. Clear middleware caches
        Cache::forget('roles.admin');
        Cache::forget('roles.super');
    }
}
