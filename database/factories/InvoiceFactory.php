<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('####'),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'status' => InvoiceStatus::Pending,
            'notes' => null,
            'approved_by' => null,
            'approved_at' => null,
            'paid_at' => null,
        ];
    }
}
