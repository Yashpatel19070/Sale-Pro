<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'name' => fake()->randomElement(['Service Fee', 'Handling Fee', 'Processing Fee']),
            'amount' => fake()->randomFloat(2, 5, 50),
        ];
    }
}
