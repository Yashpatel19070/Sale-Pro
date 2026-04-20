<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PurchaseOrderPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = [
            Permission::PURCHASE_ORDERS_VIEW_ANY,
            Permission::PURCHASE_ORDERS_VIEW,
            Permission::PURCHASE_ORDERS_CREATE,
            Permission::PURCHASE_ORDERS_UPDATE,
            Permission::PURCHASE_ORDERS_CONFIRM,
            Permission::PURCHASE_ORDERS_CANCEL,
            Permission::PURCHASE_ORDERS_REOPEN,
        ];

        foreach ($allPermissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin',  'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin',        'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager',      'guard_name' => 'web']);
        $procurement = Role::firstOrCreate(['name' => 'procurement',  'guard_name' => 'web']);

        foreach ([$superAdmin, $admin] as $role) {
            $role->givePermissionTo($allPermissions);
        }

        $manager->givePermissionTo([
            Permission::PURCHASE_ORDERS_VIEW_ANY,
            Permission::PURCHASE_ORDERS_VIEW,
            Permission::PURCHASE_ORDERS_CONFIRM,
            Permission::PURCHASE_ORDERS_CANCEL,
            Permission::PURCHASE_ORDERS_REOPEN,
        ]);

        $procurement->givePermissionTo([
            Permission::PURCHASE_ORDERS_VIEW_ANY,
            Permission::PURCHASE_ORDERS_VIEW,
            Permission::PURCHASE_ORDERS_CREATE,
            Permission::PURCHASE_ORDERS_UPDATE,
        ]);
    }
}
