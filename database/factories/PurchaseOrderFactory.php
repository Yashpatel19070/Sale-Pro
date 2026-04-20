<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'po_number' => sprintf('PO-%d-%s', now()->year, $this->faker->unique()->numerify('####')),
            'type' => PoType::Purchase,
            'parent_po_id' => null,
            'supplier_id' => Supplier::factory(),
            'status' => PoStatus::Draft,
            'skip_tech' => false,
            'skip_qa' => false,
            'reopen_count' => 0,
            'reopened_at' => null,
            'notes' => null,
            'created_by_user_id' => User::factory(),
            'confirmed_at' => null,
            'closed_at' => null,
            'cancelled_at' => null,
            'cancel_notes' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => PoStatus::Open, 'confirmed_at' => now()]);
    }

    public function partial(): static
    {
        return $this->state(['status' => PoStatus::Partial, 'confirmed_at' => now()]);
    }

    public function closed(): static
    {
        return $this->state(['status' => PoStatus::Closed, 'confirmed_at' => now(), 'closed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => PoStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function returnType(): static
    {
        return $this->state(['type' => PoType::Return]);
    }
}
