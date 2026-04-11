<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::factory()->count(10)->create();
        Customer::factory()->count(5)->inactive()->create();
        Customer::factory()->count(2)->blocked()->create();
    }
}
