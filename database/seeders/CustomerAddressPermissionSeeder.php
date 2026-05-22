<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

class CustomerAddressPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::CUSTOMER_ADDRESSES_VIEW_ANY,
            Permission::CUSTOMER_ADDRESSES_VIEW,
            Permission::CUSTOMER_ADDRESSES_CREATE,
            Permission::CUSTOMER_ADDRESSES_UPDATE,
            Permission::CUSTOMER_ADDRESSES_DELETE,
            Permission::CUSTOMER_ADDRESSES_SET_DEFAULT,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::findByName('admin', 'web');
        $manager = Role::findByName('manager', 'web');
        $sales = Role::findByName('sales', 'web');

        $admin->givePermissionTo($permissions);

        $manager->givePermissionTo([
            Permission::CUSTOMER_ADDRESSES_VIEW_ANY,
            Permission::CUSTOMER_ADDRESSES_VIEW,
            Permission::CUSTOMER_ADDRESSES_CREATE,
            Permission::CUSTOMER_ADDRESSES_UPDATE,
            Permission::CUSTOMER_ADDRESSES_SET_DEFAULT,
        ]);

        $sales->givePermissionTo([
            Permission::CUSTOMER_ADDRESSES_VIEW_ANY,
            Permission::CUSTOMER_ADDRESSES_VIEW,
        ]);
    }
}
