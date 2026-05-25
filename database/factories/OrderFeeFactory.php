<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderFee>
 */
class OrderFeeFactory extends Factory
{
    protected $model = OrderFee::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'name' => 'Service Fee',
            'amount' => 10.00,
        ];
    }
}
