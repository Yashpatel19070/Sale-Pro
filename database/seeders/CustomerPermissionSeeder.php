<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CustomerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'customers.viewAny',
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'customers.changeStatus',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $staff = Role::firstOrCreate(['name' => 'staff',       'guard_name' => 'web']);

        $superAdmin->givePermissionTo($permissions);
        $admin->givePermissionTo($permissions);

        $staff->givePermissionTo([
            'customers.viewAny',
            'customers.view',
        ]);
    }
}
