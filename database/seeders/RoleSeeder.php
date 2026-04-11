<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
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
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Create roles and assign permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
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
        ]);

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions([
            Permission::USERS_VIEW_ANY,
            Permission::USERS_VIEW,
            Permission::USERS_EDIT,
            Permission::DEPARTMENTS_VIEW_ANY,
            Permission::DEPARTMENTS_VIEW,
        ]);

        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $sales->syncPermissions([
            Permission::USERS_VIEW,
            Permission::USERS_EDIT,
        ]);
    }
}
