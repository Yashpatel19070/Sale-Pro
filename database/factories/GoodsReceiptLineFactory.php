<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoodsReceiptLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'purchase_order_line_id' => PurchaseOrderLine::factory(),
            'qty_received' => fake()->randomFloat(2, 1, 20),
            'notes' => null,
        ];
    }
}
