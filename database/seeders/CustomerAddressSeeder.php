<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Services\CustomerAddressService;
use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Seeder;

class CustomerAddressSeeder extends Seeder
{
    public function __construct(private readonly CustomerAddressService $service) {}

    public function run(): void
    {
        $fields = [
            'label', 'first_name', 'last_name', 'email', 'phone',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
        ];

        Customer::query()->chunk(200, function ($customers) use ($fields) {
            foreach ($customers as $customer) {
                $count = fake()->numberBetween(1, 3);
                $first = null;

                for ($i = 0; $i < $count; $i++) {
                    $data = CustomerAddressFactory::new()->make(['customer_id' => $customer->id])->only($fields);
                    $address = $customer->addresses()->create($data);

                    if ($first === null) {
                        $first = $address;
                    }
                }

                if ($first !== null) {
                    $this->service->setDefault($first);
                }
            }
        });
    }
}
