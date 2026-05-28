<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrderLine;
use App\Models\OrderLineFee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderLineFeeFactory extends Factory
{
    protected $model = OrderLineFee::class;

    public function definition(): array
    {
        return [
            'order_line_id' => OrderLine::factory(),
            'name' => 'Programming Fee',
            'amount' => 40.00,
            'tax_amount' => 3.30,
            'fee_total' => 43.30,
            'created_by' => User::factory(),
        ];
    }

    public function programming(): static
    {
        return $this->state([
            'name' => 'Programming Fee',
            'amount' => 40.00,
            'tax_amount' => 3.30,
            'fee_total' => 43.30,
        ]);
    }

    public function gasTuning(): static
    {
        return $this->state([
            'name' => 'Gas Tuning Fee',
            'amount' => 25.00,
            'tax_amount' => 2.06,
            'fee_total' => 27.06,
        ]);
    }
}
