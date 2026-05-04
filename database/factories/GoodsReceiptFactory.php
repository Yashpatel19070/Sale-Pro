<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GoodsReceiptStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoodsReceiptFactory extends Factory
{
    public function definition(): array
    {
        static $seq = 1;

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'grn_number' => 'GRN-2026-'.str_pad((string) $seq++, 4, '0', STR_PAD_LEFT),
            'received_by' => User::factory(),
            'received_date' => now()->toDateString(),
            'notes' => null,
            'status' => GoodsReceiptStatus::Draft,
        ];
    }
}
