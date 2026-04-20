<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PipelinePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = [
            Permission::PIPELINE_VIEW_ANY,
            Permission::PIPELINE_RECEIVE,
            Permission::PIPELINE_VISUAL,
            Permission::PIPELINE_SERIAL_ASSIGN,
            Permission::PIPELINE_TECH,
            Permission::PIPELINE_QA,
            Permission::PIPELINE_SHELF,
        ];

        foreach ($allPermissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin',  'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin',        'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager',      'guard_name' => 'web']);
        $procurement = Role::firstOrCreate(['name' => 'procurement',  'guard_name' => 'web']);
        $warehouse = Role::firstOrCreate(['name' => 'warehouse',    'guard_name' => 'web']);
        $tech = Role::firstOrCreate(['name' => 'tech',         'guard_name' => 'web']);
        $qa = Role::firstOrCreate(['name' => 'qa',           'guard_name' => 'web']);

        foreach ([$superAdmin, $admin, $manager] as $role) {
            $role->givePermissionTo([Permission::PIPELINE_VIEW_ANY]);
        }

        $procurement->givePermissionTo([Permission::PIPELINE_VIEW_ANY, Permission::PIPELINE_RECEIVE]);

        $warehouse->givePermissionTo([
            Permission::PIPELINE_VIEW_ANY,
            Permission::PIPELINE_VISUAL,
            Permission::PIPELINE_SERIAL_ASSIGN,
            Permission::PIPELINE_SHELF,
        ]);

        $tech->givePermissionTo([Permission::PIPELINE_VIEW_ANY, Permission::PIPELINE_TECH]);

        $qa->givePermissionTo([Permission::PIPELINE_VIEW_ANY, Permission::PIPELINE_QA]);
    }
}
