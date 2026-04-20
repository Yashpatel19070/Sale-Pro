<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SupplierPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::SUPPLIERS_VIEW_ANY,
            Permission::SUPPLIERS_VIEW,
            Permission::SUPPLIERS_CREATE,
            Permission::SUPPLIERS_UPDATE,
            Permission::SUPPLIERS_DELETE,
            Permission::SUPPLIERS_RESTORE,
            Permission::SUPPLIERS_CHANGE_STATUS,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo($permissions);

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->givePermissionTo([
            Permission::SUPPLIERS_VIEW_ANY,
            Permission::SUPPLIERS_VIEW,
            Permission::SUPPLIERS_CREATE,
            Permission::SUPPLIERS_UPDATE,
            Permission::SUPPLIERS_CHANGE_STATUS,
        ]);

        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $sales->givePermissionTo([
            Permission::SUPPLIERS_VIEW_ANY,
            Permission::SUPPLIERS_VIEW,
        ]);
    }
}
