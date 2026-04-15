<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InventoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::INVENTORY_VIEW_ANY,
            Permission::INVENTORY_VIEW_BY_SKU,
            Permission::INVENTORY_VIEW_BY_SKU_AT_LOCATION,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);
        Role::where('name', 'manager')->first()?->givePermissionTo($permissions);
        Role::where('name', 'sales')->first()?->givePermissionTo($permissions); // all read-only
    }
}
