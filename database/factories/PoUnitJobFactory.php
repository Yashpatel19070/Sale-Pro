<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PipelineStage;
use App\Enums\UnitJobStatus;
use App\Models\PoLine;
use App\Models\PoUnitJob;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoUnitJobFactory extends Factory
{
    protected $model = PoUnitJob::class;

    public function definition(): array
    {
        $po = PurchaseOrder::factory()->open()->create();

        return [
            'purchase_order_id' => $po->id,
            'po_line_id' => PoLine::factory()->for($po),
            'inventory_serial_id' => null,
            'pending_serial_number' => null,
            'current_stage' => PipelineStage::Receive,
            'status' => UnitJobStatus::Pending,
            'assigned_to_user_id' => null,
            'notes' => null,
        ];
    }

    public function atStage(PipelineStage $stage): static
    {
        return $this->state(['current_stage' => $stage]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => UnitJobStatus::InProgress]);
    }

    public function passed(): static
    {
        return $this->state(['status' => UnitJobStatus::Passed]);
    }

    public function failed(): static
    {
        return $this->state(['status' => UnitJobStatus::Failed]);
    }
}
