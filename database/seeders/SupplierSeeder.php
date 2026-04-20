<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['code' => 'SUP-0001', 'name' => 'Acme Electronics Ltd',   'contact_name' => 'James Reid',   'contact_email' => 'james@acme-electronics.com',  'contact_phone' => '+1-555-0101'],
            ['code' => 'SUP-0002', 'name' => 'Global Tech Supplies',   'contact_name' => 'Sara Wong',    'contact_email' => 'sara@globaltech.com',          'contact_phone' => '+1-555-0102'],
            ['code' => 'SUP-0003', 'name' => 'Pacific Components Inc', 'contact_name' => 'David Park',   'contact_email' => 'david@pacificcomp.com',        'contact_phone' => '+1-555-0103'],
            ['code' => 'SUP-0004', 'name' => 'Euro Parts Direct',      'contact_name' => 'Lena Müller',  'contact_email' => 'lena@europarts.com',           'contact_phone' => '+44-555-0104'],
            ['code' => 'SUP-0005', 'name' => 'Sunrise Distributors',   'contact_name' => 'Maria Santos', 'contact_email' => 'maria@sunrisedist.com',        'contact_phone' => '+1-555-0105'],
        ];

        foreach ($suppliers as $data) {
            Supplier::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}
