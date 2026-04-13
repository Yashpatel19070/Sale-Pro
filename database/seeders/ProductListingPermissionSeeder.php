<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProductListingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::PRODUCT_LISTINGS_VIEW_ANY,
            Permission::PRODUCT_LISTINGS_VIEW,
            Permission::PRODUCT_LISTINGS_CREATE,
            Permission::PRODUCT_LISTINGS_EDIT,
            Permission::PRODUCT_LISTINGS_DELETE,
            Permission::PRODUCT_LISTINGS_RESTORE,
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Super Admin — all permissions (null-safe: role may not exist in all envs)
        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);

        // Admin — all permissions
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);

        // Sales — view only (maps to staff-level access; tests use assignRole('sales'))
        Role::where('name', 'sales')->first()?->givePermissionTo([
            Permission::PRODUCT_LISTINGS_VIEW_ANY,
            Permission::PRODUCT_LISTINGS_VIEW,
        ]);
    }
}
