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
        $this->call(RoleSeeder::class);

        $admin = User::factory()->create([
            'name'     => 'Admin',
            'email'    => 'admin@sale-pro.test',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        $manager = User::factory()->create([
            'name'     => 'Manager',
            'email'    => 'manager@sale-pro.test',
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole('manager');

        $sales = User::factory()->create([
            'name'     => 'Sales Rep',
            'email'    => 'sales@sale-pro.test',
            'password' => Hash::make('password'),
        ]);
        $sales->assignRole('sales');
    }
}
