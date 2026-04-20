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
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin',  'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin',        'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager',      'guard_name' => 'web']);
        $procurement = Role::firstOrCreate(['name' => 'procurement',  'guard_name' => 'web']);
        $warehouse = Role::firstOrCreate(['name' => 'warehouse',    'guard_name' => 'web']);

        foreach ([$superAdmin, $admin, $manager] as $role) {
            $role->givePermissionTo($permissions);
        }

        $procurement->givePermissionTo([
            Permission::SUPPLIERS_VIEW_ANY,
            Permission::SUPPLIERS_VIEW,
        ]);

        // warehouse has no supplier permissions
    }
}
