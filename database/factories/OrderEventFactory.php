<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderEvent as OrderEventEnum;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderEventFactory extends Factory
{
    protected $model = OrderEvent::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'event' => OrderEventEnum::OrderPlaced,
            'metadata' => [
                'sku' => 'ECM-2024',
                'product_name' => 'Engine Control Module',
                'grand_total' => '286.86',
            ],
            'created_by' => User::factory(),
        ];
    }

    public function orderPlaced(): static
    {
        return $this->state([
            'event' => OrderEventEnum::OrderPlaced,
            'metadata' => [
                'sku' => 'ECM-2024',
                'product_name' => 'Engine Control Module',
                'grand_total' => '286.86',
            ],
        ]);
    }

    public function paymentReceived(): static
    {
        return $this->state([
            'event' => OrderEventEnum::PaymentReceived,
            'metadata' => [
                'method' => 'cash',
                'amount' => '286.86',
                'shipping' => '0.00',
            ],
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'event' => OrderEventEnum::Completed,
            'metadata' => [],
        ]);
    }
}
