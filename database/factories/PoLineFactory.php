<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PoLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoLineFactory extends Factory
{
    protected $model = PoLine::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'qty_ordered' => $this->faker->numberBetween(1, 50),
            'qty_received' => 0,
            'unit_price' => $this->faker->randomFloat(2, 10, 5000),
            'snapshot_stock' => 0,
            'snapshot_inbound' => 0,
        ];
    }

    public function fulfilled(): static
    {
        return $this->state(fn (array $attrs) => ['qty_received' => $attrs['qty_ordered']]);
    }
}
