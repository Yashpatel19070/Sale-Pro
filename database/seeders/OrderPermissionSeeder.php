<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class OrderPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $all = [
            Permission::ORDERS_VIEW,
            Permission::ORDERS_CREATE,
            Permission::ORDERS_MANAGE,
        ];

        foreach ($all as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::where('name', 'super-admin')->first()?->givePermissionTo($all);
        Role::where('name', 'admin')->first()?->givePermissionTo($all);
        Role::where('name', 'manager')->first()?->givePermissionTo($all);
        Role::where('name', 'sales')->first()?->givePermissionTo([
            Permission::ORDERS_VIEW,
        ]);
    }
}
