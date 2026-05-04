<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        static $seq = 1;
        $subtotal = fake()->randomFloat(2, 100, 5000);
        $taxTotal = round($subtotal * 0.1, 2);

        return [
            'po_number' => 'PO-2026-'.str_pad((string) $seq++, 4, '0', STR_PAD_LEFT),
            'supplier_id' => Supplier::factory(),
            'created_by' => User::factory(),
            'status' => PurchaseOrderStatus::Draft,
            'expected_delivery_date' => null,
            'notes' => null,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'grand_total' => $subtotal + $taxTotal,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ];
    }
}
