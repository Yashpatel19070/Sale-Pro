<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepartmentSeeder::class,
            CustomerRoleSeeder::class,
            CustomerPermissionSeeder::class,
            ProductCategoryPermissionSeeder::class,
            CustomerSeeder::class,
            ProductCategorySeeder::class,
            ProductPermissionSeeder::class,
            ProductSeeder::class,
            ProductListingPermissionSeeder::class,
            ProductListingSeeder::class,
            AuditLogPermissionSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@sale-pro.test'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'status' => 'active',
                'timezone' => 'UTC',
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles('admin');

        $manager = User::firstOrCreate(
            ['email' => 'manager@sale-pro.test'],
            [
                'name' => 'Sales Manager',
                'password' => Hash::make('password'),
                'status' => 'active',
                'timezone' => 'UTC',
                'email_verified_at' => now(),
            ]
        );
        $manager->syncRoles('manager');

        $sales = User::firstOrCreate(
            ['email' => 'sales@sale-pro.test'],
            [
                'name' => 'Sales Rep',
                'password' => Hash::make('password'),
                'status' => 'active',
                'timezone' => 'UTC',
                'email_verified_at' => now(),
            ]
        );
        $sales->syncRoles('sales');
    }
}
