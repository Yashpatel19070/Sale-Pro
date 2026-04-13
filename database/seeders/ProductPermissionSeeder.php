<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ProductPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::PRODUCTS_VIEW_ANY,
            Permission::PRODUCTS_VIEW,
            Permission::PRODUCTS_CREATE,
            Permission::PRODUCTS_EDIT,
            Permission::PRODUCTS_DELETE,
            Permission::PRODUCTS_RESTORE,
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);

        Role::where('name', 'admin')->first()?->givePermissionTo([
            Permission::PRODUCTS_VIEW_ANY,
            Permission::PRODUCTS_VIEW,
            Permission::PRODUCTS_CREATE,
            Permission::PRODUCTS_EDIT,
            Permission::PRODUCTS_DELETE,
            Permission::PRODUCTS_RESTORE,
        ]);

        Role::where('name', 'staff')->first()?->givePermissionTo([
            Permission::PRODUCTS_VIEW_ANY,
            Permission::PRODUCTS_VIEW,
        ]);
    }
}
