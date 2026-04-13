<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuditLogPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::AUDIT_LOG_VIEW_ANY,
            Permission::AUDIT_LOG_VIEW,
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // Admin — full access
        $admin = Role::findByName('admin');
        $admin->givePermissionTo($permissions);

        // Manager and Sales — no access by default
        // Grant via role management UI if required
    }
}
