<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // Demo-only seeder — guarded so the known-password fixture never
        // ships to non-local/non-test environments.
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $tx = Customer::firstOrCreate(
            ['email' => 'texas@example.com'],
            [
                'name' => 'Texas Test Buyer',
                'phone' => '713-555-0199',
                'tax_exempt' => false,
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );

        CustomerAddress::firstOrCreate(
            ['customer_id' => $tx->id, 'label' => 'Home'],
            [
                'first_name' => 'Texas',
                'last_name' => 'Buyer',
                'address_line1' => '1100 Congress Ave',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
                'is_default' => true,
            ]
        );
    }
}
