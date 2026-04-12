<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

class ProductCategoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $all = [
            Permission::PRODUCT_CATEGORIES_VIEW_ANY,
            Permission::PRODUCT_CATEGORIES_VIEW,
            Permission::PRODUCT_CATEGORIES_CREATE,
            Permission::PRODUCT_CATEGORIES_UPDATE,
            Permission::PRODUCT_CATEGORIES_DELETE,
        ];

        foreach ($all as $name) {
            SpatiePermission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->first();
        $sales = Role::where('name', 'sales')->first();

        $admin?->givePermissionTo($all);

        $sales?->givePermissionTo([
            Permission::PRODUCT_CATEGORIES_VIEW_ANY,
            Permission::PRODUCT_CATEGORIES_VIEW,
        ]);
    }
}
