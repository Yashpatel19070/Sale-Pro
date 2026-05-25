<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    protected $model = OrderLine::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'inventory_serial_id' => InventorySerial::factory()->inStock(),
            'sku' => strtoupper($this->faker->bothify('SKU-####')),
            'product_name' => $this->faker->words(3, true),
            'unit_price' => 200.00,
            'tax_rate' => 0.00,
            'tax_amount' => 0.00,
            'line_total' => 200.00,
        ];
    }
}
