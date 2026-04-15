<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InventoryMovementPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::INVENTORY_MOVEMENTS_VIEW,
            Permission::INVENTORY_MOVEMENTS_TRANSFER,
            Permission::INVENTORY_MOVEMENTS_SELL,
            Permission::INVENTORY_MOVEMENTS_ADJUST,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);
        Role::where('name', 'manager')->first()?->givePermissionTo($permissions);

        // sales: view, transfer, sell — NOT adjust
        Role::where('name', 'sales')->first()?->givePermissionTo([
            Permission::INVENTORY_MOVEMENTS_VIEW,
            Permission::INVENTORY_MOVEMENTS_TRANSFER,
            Permission::INVENTORY_MOVEMENTS_SELL,
        ]);
    }
}
