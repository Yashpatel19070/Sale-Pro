<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InventorySerialPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::INVENTORY_SERIALS_VIEW_ANY,
            Permission::INVENTORY_SERIALS_VIEW,
            Permission::INVENTORY_SERIALS_CREATE,
            Permission::INVENTORY_SERIALS_EDIT,
            Permission::INVENTORY_SERIALS_MARK_DAMAGED,
            Permission::INVENTORY_SERIALS_MARK_MISSING,
            Permission::INVENTORY_SERIALS_VIEW_PURCHASE_PRICE,
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);
        Role::where('name', 'manager')->first()?->givePermissionTo($permissions);
        Role::where('name', 'sales')->first()?->givePermissionTo([
            Permission::INVENTORY_SERIALS_VIEW_ANY,
            Permission::INVENTORY_SERIALS_VIEW,
            Permission::INVENTORY_SERIALS_CREATE,
        ]);
    }
}
