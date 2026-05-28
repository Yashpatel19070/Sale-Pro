<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Seeder;

/**
 * Seeds three edge-case customers for verifying AvaTax behavior:
 *   1. Houston Local — same city as shop ship_from → store-local rate.
 *   2. California Out-of-State — cross-state shipping → CA rate.
 *   3. Tax-Exempt Buyer — verifies tax_exempt=true returns $0 tax.
 *
 * Demo-only: guarded so the known-password fixture never ships to non-local
 * environments.
 */
class AvaTaxTestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $houston = Customer::firstOrCreate(
            ['email' => 'houston-local@example.com'],
            [
                'name' => 'Houston Local Buyer',
                'phone' => '713-555-0201',
                'tax_exempt' => false,
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );

        CustomerAddress::firstOrCreate(
            ['customer_id' => $houston->id, 'label' => 'Home'],
            [
                'first_name' => 'Houston',
                'last_name' => 'Local',
                'address_line1' => '5426 N Shepherd Dr',
                'city' => 'Houston',
                'state' => 'TX',
                'postal_code' => '77091',
                'country' => 'US',
                'is_default' => true,
            ]
        );

        $california = Customer::firstOrCreate(
            ['email' => 'california@example.com'],
            [
                'name' => 'California Out-of-State Buyer',
                'phone' => '415-555-0301',
                'tax_exempt' => false,
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );

        CustomerAddress::firstOrCreate(
            ['customer_id' => $california->id, 'label' => 'Home'],
            [
                'first_name' => 'California',
                'last_name' => 'Buyer',
                'address_line1' => '1 Market St',
                'city' => 'San Francisco',
                'state' => 'CA',
                'postal_code' => '94105',
                'country' => 'US',
                'is_default' => true,
            ]
        );

        $exempt = Customer::firstOrCreate(
            ['email' => 'tax-exempt@example.com'],
            [
                'name' => 'Tax-Exempt Buyer',
                'phone' => '512-555-0401',
                'tax_exempt' => true,
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );

        CustomerAddress::firstOrCreate(
            ['customer_id' => $exempt->id, 'label' => 'Home'],
            [
                'first_name' => 'Tax',
                'last_name' => 'Exempt',
                'address_line1' => '500 N Lamar Blvd',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78703',
                'country' => 'US',
                'is_default' => true,
            ]
        );
    }
}
