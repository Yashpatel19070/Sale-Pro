<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderLineFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 50);
        $cost = fake()->randomFloat(2, 10, 500);
        $tax = 0;

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'description' => fake()->words(3, true),
            'qty_ordered' => $qty,
            'qty_received' => 0,
            'qty_on_hand_snapshot' => fake()->randomFloat(2, 0, 200),
            'unit_cost' => $cost,
            'tax_rate' => $tax,
            'line_total' => $qty * $cost,
        ];
    }
}
