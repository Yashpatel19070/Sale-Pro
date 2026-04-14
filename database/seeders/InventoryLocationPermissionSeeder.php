<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InventoryLocationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::INVENTORY_LOCATIONS_VIEW_ANY,
            Permission::INVENTORY_LOCATIONS_VIEW,
            Permission::INVENTORY_LOCATIONS_CREATE,
            Permission::INVENTORY_LOCATIONS_EDIT,
            Permission::INVENTORY_LOCATIONS_DELETE,
            Permission::INVENTORY_LOCATIONS_RESTORE,
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // super-admin: null-safe, future role not yet seeded
        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);

        // admin: full access
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);

        // manager: same as admin
        Role::where('name', 'manager')->first()?->givePermissionTo($permissions);

        // sales: view only
        Role::where('name', 'sales')->first()?->givePermissionTo([
            Permission::INVENTORY_LOCATIONS_VIEW_ANY,
            Permission::INVENTORY_LOCATIONS_VIEW,
        ]);
    }
}
