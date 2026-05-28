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

        $permissions = [
            'orders.viewAny',
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
            'orders.recordPayment',
            'orders.complete',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        // super-admin, admin, manager get everything
        foreach (['super-admin', 'admin', 'manager'] as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo($permissions);
        }

        // sales gets everything EXCEPT orders.delete
        Role::where('name', 'sales')->first()?->givePermissionTo(array_filter(
            $permissions,
            fn ($p) => $p !== 'orders.delete'
        ));
    }
}
