<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\ProductListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderLineFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 50, 500);

        return [
            'order_id' => Order::factory(),
            'product_listing_id' => ProductListing::factory(),
            'sku' => 'PROD-'.strtoupper(fake()->bothify('??##')),
            'product_name' => fake()->words(3, true),
            'inventory_serial_id' => null,
            'unit_price' => $unitPrice,
            'tax_rate' => 0.0000,
            'tax_amount' => 0.00,
            'line_total' => $unitPrice,
        ];
    }
}
