<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PipelineStage;
use App\Enums\UnitEventAction;
use App\Models\PoUnitEvent;
use App\Models\PoUnitJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoUnitEventFactory extends Factory
{
    protected $model = PoUnitEvent::class;

    public function definition(): array
    {
        return [
            'po_unit_job_id' => PoUnitJob::factory(),
            'stage' => PipelineStage::Receive,
            'action' => UnitEventAction::Pass,
            'user_id' => User::factory(),
            'notes' => null,
            'created_at' => now(),
        ];
    }

    public function pass(): static
    {
        return $this->state(['action' => UnitEventAction::Pass]);
    }

    public function fail(): static
    {
        return $this->state(['action' => UnitEventAction::Fail]);
    }

    public function skip(): static
    {
        return $this->state(['action' => UnitEventAction::Skip]);
    }
}
