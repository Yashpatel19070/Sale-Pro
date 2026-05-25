<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class OrderPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $all = [
            'orders.viewAny',
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.cancel',
            'orders.delete',
            'orders.pay',
            'orders.ship',
            'orders.deliver',
        ];

        foreach ($all as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminPerms = [
            'orders.viewAny', 'orders.view', 'orders.create',
            'orders.update', 'orders.cancel',
            'orders.pay', 'orders.ship', 'orders.deliver',
        ];

        $viewOnly = ['orders.viewAny', 'orders.view'];

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
            ->givePermissionTo($all);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])
            ->givePermissionTo($adminPerms);

        Role::where('name', 'manager')->first()?->givePermissionTo($adminPerms);

        Role::where('name', 'sales')->first()?->givePermissionTo($viewOnly);
    }
}
