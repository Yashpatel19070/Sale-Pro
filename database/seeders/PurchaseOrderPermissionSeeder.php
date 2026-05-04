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

        $all = [
            Permission::PURCHASE_ORDERS_VIEW_ANY,
            Permission::PURCHASE_ORDERS_VIEW,
            Permission::PURCHASE_ORDERS_CREATE,
            Permission::PURCHASE_ORDERS_UPDATE,
            Permission::PURCHASE_ORDERS_DELETE,
            Permission::PURCHASE_ORDERS_RESTORE,
            Permission::PURCHASE_ORDERS_SUBMIT,
            Permission::PURCHASE_ORDERS_APPROVE,
            Permission::PURCHASE_ORDERS_REJECT,
            Permission::PURCHASE_ORDERS_CANCEL,
            Permission::GOODS_RECEIPTS_VIEW_ANY,
            Permission::GOODS_RECEIPTS_VIEW,
            Permission::GOODS_RECEIPTS_CREATE,
            Permission::GOODS_RECEIPTS_UPDATE,
            Permission::GOODS_RECEIPTS_DELETE,
            Permission::INVOICES_VIEW_ANY,
            Permission::INVOICES_VIEW,
            Permission::INVOICES_CREATE,
            Permission::INVOICES_APPROVE,
            Permission::INVOICES_MARK_PAID,
            Permission::INVOICES_DELETE,
        ];

        foreach ($all as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $managerExclude = [
            Permission::PURCHASE_ORDERS_DELETE,
            Permission::PURCHASE_ORDERS_RESTORE,
            Permission::GOODS_RECEIPTS_DELETE,
            Permission::INVOICES_DELETE,
        ];

        $viewOnly = [
            Permission::PURCHASE_ORDERS_VIEW_ANY,
            Permission::PURCHASE_ORDERS_VIEW,
            Permission::GOODS_RECEIPTS_VIEW_ANY,
            Permission::GOODS_RECEIPTS_VIEW,
            Permission::INVOICES_VIEW_ANY,
            Permission::INVOICES_VIEW,
        ];

        Role::where('name', 'super-admin')->first()?->givePermissionTo($all);
        Role::where('name', 'admin')->first()?->givePermissionTo($all);
        Role::where('name', 'manager')->first()?->givePermissionTo(
            array_values(array_diff($all, $managerExclude))
        );
        Role::where('name', 'sales')->first()?->givePermissionTo($viewOnly);
    }
}
